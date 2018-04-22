<?php

namespace ToolsCli\Tools\Fs;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DuplicatedFiles extends Command
{
    protected function configure() : void
    {
        $this->setName('fs:duplicated')
            ->setDescription('Search files duplication and make some action on it.')
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
