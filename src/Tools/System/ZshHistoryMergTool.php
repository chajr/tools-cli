<?php

namespace ToolsCli\Tools\System;

use Symfony\Component\Console\{
    Input\InputInterface,
    Input\InputArgument,
    Output\OutputInterface,
    Helper\FormatterHelper,
};
use ToolsCli\Console\{
    Command,
    Alias,
};
use BlueRegister\{
    Register, RegisterException
};
use BlueConsole\Style;

class ZshHistoryMergTool extends Command
{
    /**
     * @var Register
     */
    protected $register;

    /**
     * @var FormatterHelper
     */
    protected $formatter;

    /**
     * @var Style
     */
    protected $blueStyle;

    /**
     * @param string $name
     * @param Alias $alias
     * @param Register $register
     */
    public function __construct(string $name, Alias $alias, Register $register)
    {
        $this->register = $register;
        parent::__construct($name, $alias);
    }

    protected function configure() : void
    {
        $this->setName('system:zsh-hist-merge')
            ->setDescription('Allow to merge multiple zsh history files with correct sort.')
            ->setHelp('');

        $this ->addArgument(
            'output',
            InputArgument::REQUIRED,
            'New .zsh_history file'
        );

        $this ->addArgument(
            'files',
            InputArgument::IS_ARRAY | InputArgument::REQUIRED,
            '.zsh_history files to merge'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->formatter = $this->register->factory(FormatterHelper::class);
            $this->blueStyle = $this->register->factory(Style::class, [$input, $output, $this->formatter]);
        } catch (RegisterException $exception) {
            throw new \Exception('RegisterException: ' . $exception->getMessage());
        }

        $this->blueStyle->infoMessage('Starting merge proces.');

        $zshFiles = $input->getArgument('files');
        $list = [];

        if (count($zshFiles) > 0) {
            $list = $this->processFiles($zshFiles);
        }

        $history = '';
        \ksort($list);

        $this->blueStyle->infoMessage('Build new history');

        foreach ($list as $key => $stamp) {
            $history = $this->buildNewHistory($stamp, $history, $key);
        }

        if (empty($history)) {
            $this->blueStyle->errorMessage('History is empty ');
            return;
        }

        $this->blueStyle->infoMessage('Processed lines: ' . \count($list));

        try {
            \file_put_contents($input->getArgument('output'), $history);
        } catch (\Throwable $exception) {
            $this->blueStyle->errorMessage('Error: save file fail: ' . $exception->getMessage());
            return;
        }

        $this->blueStyle->success('Merged history file saved.');
    }

    /**
     * @param $zshFiles
     * @return array
     */
    protected function processFiles($zshFiles): array
    {
        $list = [];

        foreach ($zshFiles as $file) {
            $this->blueStyle->infoMessage('Processing file: ' . $file);

            if (!\file_exists($file)) {
                $this->blueStyle->errorMessage('File not found: ' . $file);
            }

            $handler = \fopen($file, 'rb');
            $list = $this->processLines($list, $handler);

            if (!\feof($handler)) {
                $this->blueStyle->errorMessage('Error: unexpected fgets() fail: ' . $file);
            }

            \fclose($handler);
        }

        return $list;
    }

    /**
     * @param array $list
     * @param $handler
     * @return array
     */
    protected function processLines(array $list, $handler): array
    {
        $previousStamp = 0;

        while (($line = \fgets($handler)) !== false) {
            if ($line === "\n") {
                continue;
            }

            \preg_match('# [\d]{10,15}+#', $line, $matches);
            $stamp = \trim($matches[0] ?? null);

            if (!$stamp) {
                $list[$previousStamp][] = $line;
                continue;
            }

            $previousStamp = $stamp;

            $data = \explode(':0;', $line);
            $list[$stamp][] = $data[1] ?? 'Unresolved command';
        }

        return $list;
    }

    /**
     * @param array $stamp
     * @param string $history
     * @param int $key
     * @return string
     */
    protected function buildNewHistory(array $stamp, string $history, int $key): string
    {
        $nextNoStamp = false;

        foreach ($stamp as $line) {
            if (\preg_match("#\\\\$#", $line)) {
                $history = $this->generateLine($history, $line, $key, $nextNoStamp);
                $nextNoStamp = true;
                continue;
            }

            $history = $this->generateLine($history, $line, $key, $nextNoStamp);
            $nextNoStamp = false;
        }

        return $history;
    }

    /**
     * @param string $history
     * @param string $line
     * @param int $key
     * @param bool $nextNoStamp
     * @return string
     */
    protected function generateLine(string $history, string $line, int $key, bool $nextNoStamp): string
    {
        if ($nextNoStamp) {
            $history .= " $line";
        } else {
            $history .= ": $key:0;$line";
        }

        return $history;
    }
}
