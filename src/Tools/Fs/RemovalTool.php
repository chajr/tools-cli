<?php
/**
 * exa -a1
 * ls -A1
 * while fgets(STDIN)
 * -r -- kasuje zawartosc katalogow
 */

namespace ToolsCli\Tools\Fs;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemovalTool extends Command
{


    protected function configure() : void
    {
        $this->setName('fs:removal')
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