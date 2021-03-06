<?php

namespace Rougin\Staticka;

use Symfony\Component\Console\Tester\CommandTester;

/**
 * Watch Command Test
 *
 * @package Staticka
 * @author  Rougin Royce Gutib <rougingutib@gmail.com>
 */
class WatchCommandTest extends TestCase
{
    /**
     * Tests "watch" command.
     *
     * @return void
     */
    public function testWatchCommand()
    {
        $command = new CommandTester($this->application->find('watch'));

        $options = array('--test' => true);

        $options['--source'] = __DIR__ . '/Application';

        $options['--path'] = __DIR__ . '/Application/build';

        $command->execute($options);

        $output = $command->getDisplay();

        $this->assertContains('Site built successfully', $output);
    }
}
