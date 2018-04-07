<?php

namespace ToolsCli\Console;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\{
    Command\Command,
    Input\InputInterface,
    Output\OutputInterface,
};

class Installer extends Command
{
    protected function configure() : void
    {
        $this->setName('installer')
            ->setDescription('Install tools-cli')
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
        $fileSystem = new Filesystem;

        $fileSystem->copy('etc/', '~/.config/tools-cli/etc');
        
        //rename dist to configs
        //mkdir storage
        //mkdir log dir
        //copy build
    }
}
