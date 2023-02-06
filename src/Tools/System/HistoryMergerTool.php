<?php

declare(strict_types=1);

namespace ToolsCli\Tools\System;

use Symfony\Component\Console\{
    Input\InputInterface,
    Output\OutputInterface,
    Input\InputArgument,
};
use ToolsCli\Console\Display\Style;
use ToolsCli\Console\Command;

class HistoryMergerTool extends Command
{
    protected function configure(): void
    {
        $this->setName('system:history-merge')
            ->setDescription('Merge all give zsh history files into one')
            ->setHelp('');

        $this->addArgument(
            'files',
            InputArgument::IS_ARRAY,
            'zsh history files to merge'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $filesContent = [];
        $commandIndex = 0;
        $previousHash = '';

        foreach ($input->getArgument('files') as $file) {
            echo "process file: $file\n";
            $res = @\fopen($file, 'r');

            if (!$res) {
                throw new \RuntimeException('Unable to read file: ' . $file);
            }

            while ($line = \fgets($res)) {
                if (\preg_match('#^: [\d]+#', $line) && \preg_match('#\n$#', $line)) {
                    \preg_match('#^: \d+#', $line, $matches);
                    $timestamp =  \str_replace([': ', ':'], '', \reset($matches));
                    $commandIndex++;
                }
                $filesContent[$timestamp][$commandIndex][] = $line;
            }

            if (!feof($res)) {
                echo "Error: unexpected fgets() fail\n";
            }
            fclose($res);
        }

        echo "commands: $commandIndex\n";
        echo "sorting\n";

        \ksort($filesContent, SORT_NUMERIC);

        echo "write to file\n";

        $fileName = \date('Y-m-d-H:i:s') . '_zsh_history';
        $path = '/home/chajr/Dropbox/zsh/' . $fileName;
        \touch($path);

        foreach ($filesContent as $commandGroup) {
            foreach ($commandGroup as $command) {
                $fullCommand = \implode(';', $command);
                $hash = hash('sha3-256', $fullCommand);

                if ($hash === $previousHash) {
                    continue;
                }

                $previousHash = $hash;

                foreach ($command as $line) {
                    \file_put_contents($path, $line, FILE_APPEND);
                }
            }
        }

        echo "done";
    }

    /**
     * @param string $current
     * @param int $length
     * @return string
     */
    protected function formatLineCounter(string $current, int $length): string
    {
        $currentLength = \strlen($current);
        $diff = $length - $currentLength;

        $out = \str_repeat(' ', $diff);

        return $out . $current;
    }
}
