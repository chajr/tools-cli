<?php

namespace ToolsCli\Tools\System;

use Symfony\Component\Console\{
    Input\InputInterface,
    Output\OutputInterface,
};
use ToolsCli\Console\Display\Style;
use ToolsCli\Console\Command;

class CleanerTool extends Command
{
    /**
     * @var array
     */
    protected $config;

    protected function configure() : void
    {
        $this->setName('system:cleaner')
            ->setDescription('')
            ->setHelp('');
        
        /*
         * clear unwanted files from system (orm move to other directory)
         * files config: regular expression to search files, and time limit for that files
         * after file time is exceeded, file or dir is removed
         */
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $this->readConfig();

        foreach ($this->config as $config) {
            dump($config);
        }
    }

    protected function readConfig(): void
    {
        try {
            $config = file_get_contents(__DIR__ . '/../../var/cleaner.json');
            $this->config = \json_decode($config, true);
        } catch (\Throwable $exception) {
            throw new \RuntimeException($exception);
        }
    }
}
