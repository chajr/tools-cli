<?php

namespace ToolsCli\Tools\System;

use Symfony\Component\Console\{Input\InputArgument,
    Input\InputInterface,
    Output\OutputInterface,
    Helper\FormatterHelper};
use BlueFilesystem\StaticObjects\Fs;
use BlueRegister\{
    Register, RegisterException
};
use ToolsCli\Console\{
    Command,
    Alias,
};
use BlueConsole\Style;
use DateTime;

class BackupTool extends Command
{
    /**
     * @var array
     */
    protected $cleanerConfig = [];

    /**
     * @var Register
     */
    protected $register;

    /**
     * @var Style
     */
    protected $blueStyle;

    /**
     * @var FormatterHelper
     */
    protected $formatter;

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

    protected function configure(): void
    {
        $this->setName('system:backup')
            ->setDescription('')
            ->setHelp('');

        $this->addArgument(
            'source',
            InputArgument::REQUIRED,
            'source directory to sync'
        );

        $this->addArgument(
            'destination',
            InputArgument::REQUIRED,
            'destination directory'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->formatter = $this->register->factory(FormatterHelper::class);
            $this->blueStyle = $this->register->factory(Style::class, [$input, $output, $this->formatter]);
        } catch (RegisterException $exception) {
            throw new \UnexpectedValueException('RegisterException: ' . $exception->getMessage());
        }

        $source = $input->getArgument('source');
        $destination = $input->getArgument('destination');

        \exec("rsync -vpogthld $source $destination", $out, $code);

        $this->blueStyle->listing($out);

        if ($code === 0) {
            $iterator = new \DirectoryIterator($destination);

            /** @var \DirectoryIterator $element */
            foreach ($iterator as $element) {
                if ($element->isDir() && !$element->isDot()) {
                    $pathToRemove = $element->getPathname();
                    $this->blueStyle->infoMessage("Removing: <fg=red>$pathToRemove</>");

                    if ($element->isLink()) {
                        \exec("rm $pathToRemove", $out, $res);
                    } else {
                        $res = Fs::delete($pathToRemove);
                    }

                    if (\is_array($res) || $res === 0) {
                        $this->blueStyle->infoMessage("Path <fg=green>$pathToRemove</> removed");
                    } else {
                        $this->blueStyle->errorMessage("Path $pathToRemove not removed");
                    }
                }
            }
        }

        return 0;
    }
}
