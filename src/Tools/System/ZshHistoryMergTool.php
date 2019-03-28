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
//            $this->progressBar = $this->register->factory(ProgressBar::class, [$output]);
        } catch (RegisterException $exception) {
            throw new \Exception('RegisterException: ' . $exception->getMessage());
        }

        $zshFiles = $input->getArgument('files');
        $list = [];

        if (count($zshFiles) > 0) {
            foreach ($zshFiles as $file) {
                if (!\file_exists($file)) {
                    $this->blueStyle->errorMessage('File not found: ' . $file);
                }

                $handler = \fopen($file, 'rb');

                while (($line = \fgets($handler)) !== false) {
                    \preg_match('# [\d]{10,15}+#', $line, $matches);
                    $stamp = (int) \trim($matches[0] ?? null);

                    if (\array_key_exists($stamp, $list)) {
                        $stamp++;
                    }

                    $data = \explode(':0;', $line);
                    $list[$stamp] = $data[1] ?? 'Unresolved command';
                }

                if (!\feof($handler)) {
                    $this->blueStyle->errorMessage('Error: unexpected fgets() fail: ' . $file);
                }

                \fclose($handler);
            }
        }

        \ksort($list);
        dump($list);
        dump(count($list));
        //save array as list of commands
    }
}
