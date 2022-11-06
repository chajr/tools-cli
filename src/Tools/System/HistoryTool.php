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

class HistoryTool extends Command
{
    public const DATE_FORMAT = 'Y-m-d';

    protected bool $previousLineRendered = false;
    protected ?string $previousLine = null;
    protected array $unique = [];

    protected function configure(): void
    {
        $this->setName('system:history')
            ->setDescription('Show zsh history in some specified formats.')
            ->setHelp('');

        //unique + sort
        //grep with previous lines (or merge multiline)
        //tail & head + date
        //sort

        $this->addOption(
            'command-only',
            'c',
            null,
            'Show only command.'
        );

        $this->addOption(
            'head',
            'H',
            InputArgument::OPTIONAL,
            'Show lines from top.'
        );

        $this->addOption(
            'grep',
            'g',
            InputArgument::OPTIONAL,
            'Display only matching commands.'
        );

        $this->addOption(
            'tail',
            't',
            InputArgument::OPTIONAL,
            'Show lines from bottom.',
        );

        $this->addOption(
            'date',
            'd',
            InputArgument::OPTIONAL,
            'Show lines from given date, or date period (YYYY-MM-DD or YYYY-MM-DD:YYYY-MM-DD).',
        );

        $this->addOption(
            'history-file',
            'f',
            InputArgument::OPTIONAL,
            'Get history from custom file.',
        );

        $this->addOption(
            'unique',
            'u',
            null,
            'Get unique commands.',
        );

        $this->addOption(
            'sorta',
            's',
            null,
            'Sort command alphabetically asc.',
        );

        $this->addOption(
            'sortd',
            'S',
            null,
            'Sort command alphabetically desc.',
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
        $historyFile = getenv('TOOLS_CLI_HISTORY_FILE');

        if (!$historyFile) {
            $historyFile = '~/.zsh_history';
        }

        if ($input->getOption('history-file')) {
            $historyFile = $input->getOption('history-file');
        }

        $lineCount = 1;
        $history = \shell_exec('cat ' . $historyFile);
        $rows = \explode("\n", $history);
        $rowsCount = \count($rows);
        $allCommandsLength = \strlen((string)$rowsCount);
        $style = new Style($input, $output, $this);
        $errors = [];

        if ($input->getOption('head') && !$input->getOption('date')) {
            $val = $input->getOption('head');
            $allCommandsLength = \strlen((string)$val);
            $rows = \array_slice($rows, 0, (int)$val);
        }

        if ($input->getOption('tail') && !$input->getOption('date')) {
            $val = (int)$input->getOption('tail');
            $index = $rowsCount - $val;
            $rows = \array_slice($rows, $index, $val);
            $lineCount = $index + 1;
        }

        foreach ($rows as $row) {
            try {
                $matches = [];
                $expression = \preg_split('#(:[\d]+;)#', $row, -1, PREG_SPLIT_DELIM_CAPTURE);

                if ($this->previousLineRendered && \count($expression) === 1 && $expression[0] !== '') {
                    $style->writeln(\preg_replace('#\\\\\\\$#', '\\', $expression[0]));
                    $this->previousLineRendered = true;
                    $this->previousLine .= ' ' . $expression[0];
                    continue;
                }

                $dateTimeExpression = \preg_match('#^: \d+#', \reset($expression), $matches);

                if (!$dateTimeExpression) {
                    $this->previousLineRendered = false;
                    continue;
                }

                $dateTime = \str_replace([': ', ':'], '', \reset($matches));

                \array_shift($expression);
                \array_shift($expression);
                $expression = \implode('', $expression);

                if ($input->getOption('date')) {
                    $dates = \explode(':', $input->getOption('date'));
                    $date = \date(self::DATE_FORMAT, (int)$dateTime);
                    $date1 = \DateTime::createFromFormat(self::DATE_FORMAT, $date);

                    if (\count($dates) > 1) {
                        $date2 = \DateTime::createFromFormat(self::DATE_FORMAT, $dates[0]);
                        $date3 = \DateTime::createFromFormat(self::DATE_FORMAT, $dates[1]);

                        if ($date1 < $date2 || $date1 > $date3) {
                            $this->previousLineRendered = false;
                            continue;
                        }
                    } else {
                        $date2 = \DateTime::createFromFormat(self::DATE_FORMAT, $dates[0]);

                        if ($date1 != $date2) {
                            $this->previousLineRendered = false;
                            continue;
                        }
                    }
                }

                if ($input->getOption('grep')) {
                    $match = \preg_match('#' . $input->getOption('grep') . '#', $expression, $matches);

                    if ($match) {
                        foreach ($matches as $index => $matchPart) {
                            $color = 'blue';
                            if ($index === 0) {
                                $color = 'red';
                            }
                            $expression = \str_replace($matchPart, "<fg=$color>$matchPart</>", $expression);
                        }
                    } else {
                        $this->previousLineRendered = false;
                        continue;
                    }
                }

                $adds = '';
                if (!$input->getOption('command-only')) {
                    $date = \date('Y-m-d H:m:s', (int)$dateTime);
                    $lineNumber = $this->formatLineCounter((string)$lineCount, $allCommandsLength);

                    $adds = "[<fg=blue>$lineNumber</> <info>$date</info>] ";
                }

                if ($input->getOption('unique')) {
                    $commandToDisplay = \hash('sha3-256', $expression);
                    if (\in_array($commandToDisplay, $this->unique, true)) {
                        $this->previousLineRendered = false;
                        continue;
                    }

                    $this->unique[] = $commandToDisplay;
                }

                $style->writeln($adds . \preg_replace('#\\\\\\\$#', '\\', $expression));
                $this->previousLineRendered = true;
                if (\preg_match('#\\\\\\\$#', $expression)) {
                    $this->previousLine = $expression;
                } else {
                    $this->previousLine = null;
                }
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
