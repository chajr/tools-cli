<?php

namespace ToolsCli\Tools\System;

use Symfony\Component\Console\{
    Command\Command,
    Input\InputInterface,
    Input\InputArgument,
    Output\OutputInterface,
    Helper\FormatterHelper,
    Helper\ProgressBar,
};

class ZshHistoryMergTool extends Command
{
    protected function configure() : void
    {
        $this->setName('system:zsh-hist-merge')
            ->setDescription('Allow to merge multiple zsh history files with correct sort.')
            ->setHelp('');

        $this ->addArgument(
            'output',
            InputArgument::REQUIRED,
            'New .zsh_history file'
        );

        $this ->addArgument(
            'files',
            InputArgument::IS_ARRAY | InputArgument::REQUIRED,
            '.zsh_history files to merge'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $zshFiles = $input->getArgument('files');
        if (count($zshFiles) > 0) {
            foreach ($zshFiles as $file) {
                
            }
        }
        //get all zhs files
        //build array
        //convert date to timestamp
        //merge arrays
        //if key exists, add +1s
        //save array as list of commands
    }
}
