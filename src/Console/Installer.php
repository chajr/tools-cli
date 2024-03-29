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

        $fileSystem->mkdir('/etc/toolscli/');
        $fileSystem->copy('etc/*', '/etc/toolscli/');
        $fileSystem->mkdir('~/.config/tools-cli/storage');
        $fileSystem->mkdir('~/.config/tools-cli/log');
    }
}
