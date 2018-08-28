<?php

namespace ToolsCli\Tools\Utils;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OperaHistoryParserTool extends Command
{
    #!/usr/bin/env bash
    
    //fileName=`date +"%d.%m.%Y-%T"`
    //cp "$HOME/Library/Application Support/com.operasoftware.Opera/Last Session" "$HOME/opera-backups/$fileName-last"
    //cp "$HOME/Library/Application Support/com.operasoftware.Opera/Current Session" "$HOME/opera-backups/$fileName-current"
    //
    //find $HOME/opera-backups/ -type f -mtime +5d -exec rm {} \;

    protected function configure() : void
    {
        $this->setName('utils:opera-history-parser')
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
        $content = file_get_contents('session');
        $find = [];
        preg_match_all('/((http(s)?)|(chrome-extension))[a-zA-Z0-9_~:\\/?#\\[\\]@!$&\'()%*+,;=`.-]+/', $content, $find);//\x00$

        $list = array_unique($find[0]);

        foreach ($list as $found) {
            $output->writeln($found);
            $output->writeln('============================================');
        }
    }
}
