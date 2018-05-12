<?php

namespace ToolsCli\Tools\System;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class History extends Command
{
    protected function configure() : void
    {
        $this->setName('system:history')
            ->setDescription('Show zsh history in some specified formats.')
            ->setHelp('');
        
        //limit (head, tail)
        //part
        //time format
        //time period
        //show mem and time usage
        //show counter (summ all comands to set propper counter format)
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $history = shell_exec('cat ~/.zsh_history');
        $rows = explode("\n", $history);

        foreach ($rows as $row) {
            $matches = [];
            $expression = explode(':0;', $row);
            $dateTimeExpression = preg_match('#^: [\d]+#', reset($expression), $matches);

            if (!$dateTimeExpression) {
                continue;
            }

            $dateTime = str_replace([': ', ':'], '', reset($matches));

            //output
            echo strftime('[%Y-%m-%d %H:%M:%S]', $dateTime) . ' ' . $expression[1] . "\n";
        }
    }
}
