<?php

namespace ToolsCli\Tools\Git;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ToolsCli\Console\Command;

class VersionTool extends Command
{
    protected $commandName = 'git:version';

    protected function configure() : void
    {
        $this->setName($this->commandName)
            ->setDescription($this->getAlias() . 'Automatic lib/app version update with git push.')
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
        $dir = getcwd();
        $composer = json_decode(
            file_get_contents($dir . '/composer.json')
        );
        /**
         * update composer
         * check changelog
         * check if current branch is develop
        git push origin develop
        checkout to master
        git merge develop
        git push origin master
        git tag $TAG
        git push --tags
        git checkout develop
         */
    }
}
