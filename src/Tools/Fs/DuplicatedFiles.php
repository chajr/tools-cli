<?php

namespace ToolsCli\Tools\Fs;

use Symfony\Component\Console\{
    Input\InputInterface,
    Input\InputArgument,
    Output\OutputInterface,
};
use ToolsCli\Console\Command;
use ToolsCli\Console\Display\Style;

class DuplicatedFiles extends Command
{
    protected function configure() : void
    {
        $this->setName('fs:duplicated')
            ->setDescription('Search files duplication and make some action on it.')
            ->setHelp('');

        $this->addArgument(
            'source',
            InputArgument::REQUIRED,
            'source files to convert'
        );

        $this->addOption(
            'interactive',
            'i',
            null,
            'ask for deletion of duplicated files'
        );
        
        //inverse mode (default select to delete, in inverse select to keep)
        
        //symfony interract checkbox like yoman

        //skipdir??
        //generate html file with buttons to delete duplicated files
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //generate html file with possibility to delete file
        
        //fduper -r (-S)
        
        //shell_exec ...
        
        //select 1    1,2,3    1-6   a
        
        //count files all + deleted
    }
}
