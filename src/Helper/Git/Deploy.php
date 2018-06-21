<?php
/**
 * commit with message (optionally)
 * merge to master
 * push to origin
 * set tag
 * deploy tag
 *
 * checkout develop
 */

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ToolsCli\Console\Command;

class Deploy extends Command
{
    protected $commandName = 'git:deploy';

    protected function configure() : void
    {
        $this->setName($this->commandName)
            ->setDescription($this->getAlias() . 'Info.')
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
        echo 'ok';
    }
}
