<?php

namespace ToolsCli\Tools\Utils;

use Symfony\Component\Console\{
    Input\InputInterface,
    Output\OutputInterface,
    Helper\FormatterHelper,
};
use BlueRegister\{
    Register, RegisterException
};
use ToolsCli\Console\{
    Command,
    Alias,
};
use BlueConsole\Style;

class HashAlgosTool extends Command
{
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

    protected function configure() : void
    {
        $this->setName('utils:hash-algos')
            ->setDescription('Show information about available hashing algorithms and execution time')
            ->setHelp('');

        //@todo sort by time, size & name
        //@todo executions
        //@todo sample data (file or text)
        //only list, no messages
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        try {
            $this->formatter = $this->register->factory(FormatterHelper::class);
            $this->blueStyle = $this->register->factory(Style::class, [$input, $output, $this->formatter]);
        } catch (RegisterException $exception) {
            throw new \UnexpectedValueException('RegisterException: ' . $exception->getMessage());
        }

        $this->blueStyle->toggleShowTimer();

        $algList = \hash_algos();

        $this->blueStyle->okMessage('Start profiling ' . \count($algList) . ' algorithms.');

        foreach ($algList as $alg) {
            $time = \microtime(true);
            $hash = '';

//            $this->blueStyle->infoMessage("Profiling: $alg");
            for ($i = 0; $i <= 100; $i++) {
                $hash = \hash_file($alg, __DIR__ . '/../../../composer.lock');
            }

            $endTime = \microtime(true);
            $diff = $endTime - $time;
            $hashLen = \strlen($hash);
            $this->blueStyle->infoMessage("$diff - $alg - $hashLen");
//            $this->blueStyle->writeln("$diff - $alg - $hashLen");
        }
    }
}
