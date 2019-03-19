<?php

namespace ToolsCli\Tools\System;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ZshHistoryMergTool extends Command
{
    protected function configure() : void
    {
        $this->setName('system:zsh-hist-merge')
            ->setDescription('Allow to merge multiple zsh history files with correct sort.')
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
