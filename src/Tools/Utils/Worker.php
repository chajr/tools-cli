<?php

namespace ToolsCli\Tools\Utils;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Worker extends Command
{
    //command
    //worker sleep (bigger than 0.1s)


    protected function configure() : void
    {
        $this->setName('utils:worker')
            ->setDescription('Info.')
            ->setHelp('');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

    }
}
