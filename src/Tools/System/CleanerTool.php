<?php

namespace ToolsCli\Tools\System;

use Symfony\Component\Console\{
    Input\InputInterface,
    Output\OutputInterface,
    Helper\FormatterHelper,
};
use BlueFilesystem\StaticObjects\Structure;
use BlueRegister\{
    Register, RegisterException
};
use ToolsCli\Console\{
    Command,
    Alias,
};
use BlueConsole\Style;
use DateTime;

class CleanerTool extends Command
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
        $this->cleanerConfig = $this->readConfig('cleaner')['list'];

        parent::__construct($name, $alias);
    }

    protected function configure(): void
    {
        $this->setName('system:cleaner')
            ->setDescription('')
            ->setHelp('');
        
        /*
         * @todo allow to create own rule class/method
         * @todo copy action
         * @todo for copy and move change filename
         * @todo follow symlinks option
         * @todo add run command befor & run command after
         * @todo in rule check recursiv or not
         * @todo in rule check type (file/dir)
         */
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

        $message = 'Execution time: ' . (new DateTime())->format('c');
        $this->blueStyle->infoMessage($message);

        foreach ($this->cleanerConfig as $config) {
            if (\is_array($config['path'])) {
                foreach ($config['path'] as $path) {
                    $this->processPath($path, $config);
                }
            } else {
                $this->processPath($config['path'], $config);
            }
        }

        return 0;
    }

    /**
     * @param string $path
     * @param array $config
     * @return void
     * @throws \Exception
     */
    protected function processPath(string $path, array $config): void
    {
        $this->blueStyle->infoMessage(
            '<options=bold>'  . $config['name']
            . '</>, path: <fg=yellow>' . $path
            . '</>, action: <fg=green>' . $config['action'] . '</>'
        );
        $this->executeAction($path, $config);
    }

    /**
     * @param string $path
     * @param array $config
     * @throws \Exception
     */
    protected function executeAction(string $path, array $config): void
    {
        try {
            $callback = $this->processElementFunction($config);

            $recursive = $config['params']['recursive'] ?? false;

            $structure = $this->register->factory(Structure::class, [$path, $recursive]);
            $structure->getReadDirectory();
            $structure->processSplObjects($callback);
        } catch (RegisterException $exception) {
            throw new \UnexpectedValueException('RegisterException: ' . $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->blueStyle->errorMessage($exception->getMessage());
        }
    }

    /**
     * @param array $config
     * @return callable
     * @throws RegisterException
     */
    protected function processElementFunction(array $config): callable
    {
        if (\preg_match('/^custom:[a-zA-Z0-9\\\]+/', $config['action'])) {
            $actionClass = \str_replace('custom:', '', $config['action']);
        } else {
            $actionClass = 'ToolsCli\Tools\System\CleanerAction\\' . \ucfirst($config['action']);
        }

        /** @var \ToolsCli\Tools\System\CleanerAction\Action $action */
        $action = $this->register->factory($actionClass, [$config, $this->blueStyle, $this->register]);

        return $action->getCallback();
    }
}
