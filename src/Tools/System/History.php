<?php

namespace ToolsCli\Tools\System;

use Symfony\Component\Console\{
    Input\InputInterface,
    Output\OutputInterface,
};
use ToolsCli\Console\Display\Style;
use ToolsCli\Console\Command;

class History extends Command
{
    protected function configure() : void
    {
        $this->setName('system:history')
            ->setDescription('Show zsh history in some specified formats.')
            ->setHelp('');

        //limit (head, tail)
        //part (commands 10-100)
        //time format
        //time period
        //show mem and time usage
        //add try/catch for each iteration, display error at end of history
        //unique + sort

        $this->addOption(
            'command-only',
            'c',
            null,
            'Show only command.'
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
        $lineCount = 1;
        $history = shell_exec('cat ~/.zsh_history');
        $rows = explode("\n", $history);
        $allCommandsLength = \strlen(count($rows));
        $style = new Style($input, $output, $this);
        $errors = [];

        foreach ($rows as $row) {
            try {
//                if (in_array($lineCount, [5, 123, 4545, 3434])) {
//                    throw new \Exception('effddfsdsfs');
//                }
                $matches = [];
                $expression = explode(':0;', $row);
                $dateTimeExpression = preg_match('#^: [\d]+#', reset($expression), $matches);

                if (!$dateTimeExpression) {
                    continue;
                }

                $adds = '';
                $dateTime = str_replace([': ', ':'], '', reset($matches));
                if (!$input->getOption('command-only')) {
                    $date = strftime('%Y-%m-%d %H:%M:%S', $dateTime);
                    $lineNumber = $this->formatLineCounter($lineCount, $allCommandsLength);

                    $adds = "[<comment>$lineNumber</comment>; <info>$date</info>] ";
                }

                $style->writeln($adds . $expression[1]);
            } catch (\Exception $exception) {
                $errors[$lineCount] = $exception;
            } finally {
                $lineCount++;
            }
        }

        if (!empty($errors)) {
            $style->newLine();
            $style->writeln('<comment>Errors during process some lines:</comment>');
        }
        foreach ($errors as $line => $error) {
            $style->writeln("<error>Line: $line; " . $error->getMessage() . '</error>');
        }
    }

    /**
     * @param int $current
     * @param int $length
     * @return string
     */
    protected function formatLineCounter(int $current, int $length) : string
    {
        $currentLength = \strlen($current);
        $diff = $length - $currentLength;
        $out = '';

        for ($i = 0; $i < $diff; $i++) {
            $out .= ' ';
        }

        return $out . $current;
    }
}
