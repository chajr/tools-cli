<?php

namespace ToolsCli\Tools\System;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SystemTool extends Command
{
    //suspend
    //hiberante
    //etc
    //systemctl suspend -i //after period
    //shutdown -h $time
    
    //set wait time

    protected function configure() : void
    {
        $this->setName('system:system')
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
