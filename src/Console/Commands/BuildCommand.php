<?php

namespace Rougin\Staticka\Console\Commands;

use Rougin\Slytherin\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Rougin\Staticka\Generator;
use Rougin\Staticka\Settings;
use Rougin\Staticka\Utility;

/**
 * Build Command
 *
 * @package Staticka
 * @author  Rougin Royce Gutib <rougingutib@gmail.com>
 */
class BuildCommand extends \Symfony\Component\Console\Command\Command
{
    /**
     * @var \Rougin\Slytherin\Container\ContainerInterface
     */
    protected $container;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * Initializes the command instance.
     *
     * @param \Rougin\Slytherin\Container\ContainerInterface $container
     * @param string|null                                    $name
     */
    public function __construct(ContainerInterface $container, $name = null)
    {
        parent::__construct($name);

        $this->container = $container;
    }

    /**
     * Configures the current command.
     *
     * @return void
     */
    public function configure()
    {
        $this->setName('build')->setDescription('Build a site from source');

        $this->addOption('source', null, 4, 'Source of the site', getcwd());
        $this->addOption('path', null, 4, 'Path of the site to be built', getcwd() . '/build');
    }

    /**
     * Executes the current command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface   $input
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        list($this->input, $this->output) = array($input, $output);

        list($site, $build, $settings) = $this->settings();

        $output->writeln('<info>Building the new site...</info>');

        $this->script($site, $settings->scripts('before'));

        $this->generate($settings, $site, $build);

        $assets = Utility::path($site . '/assets');

        file_exists($assets) && Utility::transfer($assets, $build);

        $this->script($site, $settings->scripts('after'));

        $this->filters($settings, $build);

        $output->writeln('<info>Site built successfully!</info>');
    }

    /**
     * Runs the specified filters to the built site.
     *
     * @param  \Rougin\Staticka\Settings $settings
     * @param  string                    $path
     * @return void
     */
    protected function filters(Settings $settings, $path)
    {
        $this->output->writeln('<info>Running filters...</info>');

        foreach ($settings->get('filters') as $filter) {
            $filter = $this->container->get($filter);

            $files = $this->match($path, $filter->tags());

            foreach ((array) $files as $file) {
                $content = $filter->filter(file_get_contents($file));

                file_put_contents($file, $content);

                rename($file, $filter->rename($file));
            }
        }
    }

    /**
     * Adds all defined integrations and runs the generator.
     *
     * @param  \Rougin\Staticka\Settings $settings
     * @param  string                    $site
     * @param  string                    $build
     * @return void
     */
    protected function generate(Settings $settings, $site, $build)
    {
        list($config, $container) = array($settings->config(), $this->container);

        foreach ($settings->get('integrations') as $integration) {
            $integration = new $integration;

            $container = $integration->define($container, $config);
        }

        $generator = new Generator($container, $settings);

        $generator->make($site, $build);
    }

    /**
     * Matches the files against a specified tag.
     *
     * @param  string $path
     * @param  string $tags
     * @param  array  $files
     * @return array
     */
    protected function match($path, array $tags, $files = array())
    {
        $iterator = Utility::files($path);

        foreach ($tags as $tag) {
            $pattern = '/.' . $tag . '$/';

            $regex = new \RegexIterator($iterator, $pattern);

            $items = array_values(iterator_to_array($regex));

            foreach ((array) $items as $item) {
                $file = $item->isDir() === false;

                $file && $files[] = $item->getRealpath();
            }
        }

        return $files;
    }

    /**
     * Displays the script and run it using exec().
     *
     * @param  string $source
     * @param  string $scripts
     * @return void
     */
    protected function script($source, $scripts)
    {
        $message = 'Running script "' . $scripts . '"...';

        $scripts && $this->output->writeln('<info>' . $message . '</info>');

        $scripts && exec('cd ' . $source . ' && ' . $scripts);
    }

    /**
     * Returns the source path, build path, and a Settings instance.
     *
     * @return array
     */
    protected function settings()
    {
        $site = realpath($this->input->getOption('source'));

        $build = realpath($this->input->getOption('path')) ?: $site . '/build';

        $settings = new Settings;

        $settings = $settings->load($site . '/staticka.php');

        return array($site, $build, $settings);
    }
}
