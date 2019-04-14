<?php

namespace ToolsCli\Tools\System;

use Symfony\Component\Console\{
    Input\InputInterface,
    Output\OutputInterface,
};
use ToolsCli\Console\Display\Style;
use ToolsCli\Console\Command;
use BlueFilesystem\Fs;

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
         * move files to specified destination
         * special file with custom actions
         * accept all php datetime formats (https://www.php.net/manual/en/datetime.formats.php)
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
            $this->executeAction($config);
        }
    }

    protected function executeAction(array $config): void
    {
        $readPath = Fs::readDirectory($config['path'], $config['params']['recursive']);

        foreach ($readPath as $pathVal) {
            dump($pathVal);
        }
    }

    protected function readConfig(): void
    {
        try {
            $baseConfig = file_get_contents(__DIR__ . '/../../../etc/cleaner.json');
            $this->config = \json_decode($baseConfig, true)['list'];
            //@todo add read from main /etc dir
        } catch (\Throwable $exception) {
            throw new \RuntimeException($exception);
        }
    }
}
