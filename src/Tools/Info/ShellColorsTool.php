<?php

namespace ToolsCli\Tools\Info;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ToolsCli\Console\Command;

class ShellColorsTool extends Command
{
    protected $commandName = 'info:colors';

    protected function configure() : void
    {
        $this->setName($this->commandName)
            ->setDescription($this->getAlias() . 'Show available 256 shell colors.')
            ->setHelp('');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output) : void
    {
        //https://en.wikipedia.org/wiki/ANSI_escape_code

        //foreground
        for ($i = 0; $i <= 255; $i++) {
            printf("\e[38;5;%sm %3d \e[0m ", $i, $i);

            if (($i === 15  || $i > 15 ) && ( ($i-15) % 6 === 0 )) {
                echo "\n";
            }
        }

        //background
        for ($i = 0; $i <= 255; $i++) {
            printf("\e[48;5;%sm %3d \e[0m ", $i, $i);

            if (($i === 15  || $i > 15 ) && ( ($i-15) % 6 === 0 )) {
                echo "\n";
            }
        }
        /**
         * 38 - foreground
         * 48 - background
         */

        //formatting
        for ($i = 0; $i <= 107; $i++) {
            printf("\e[%sm %3d \e[0m ", $i, $i);

            if (($i === 15  || $i > 15 ) && ( ($i-15) % 6 === 0 )) {
                echo "\n";
            }
        }
    }
}
