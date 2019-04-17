<?php

namespace ToolsCli\Tools\System;

use Symfony\Component\Console\{
    Input\InputInterface,
    Output\OutputInterface,
};
use ToolsCli\Console\Display\Style;
use BlueFilesystem\StaticObjects\Structure;
use BlueRegister\{
    Register, RegisterException
};
use ToolsCli\Console\{
    Command,
    Alias,
};

class CleanerTool extends Command
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var Register
     */
    protected $register;

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
        $this->setName('system:cleaner')
            ->setDescription('')
            ->setHelp('');
        
        /*
         * size, time (mod, create), regex, extension rules
         * special file with custom actions
         * accept all php datetime formats (https://www.php.net/manual/en/datetime.formats.php)
         */
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
        $this->readConfig();

        try {
            $this->blueStyle = $this->register->factory(Style::class, [$input, $output, $this->formatter]);
        } catch (RegisterException $exception) {
            throw new \Exception('RegisterException: ' . $exception->getMessage());
        }

        foreach ($this->config as $config) {
            $this->executeAction($config);
        }
    }

    /**
     * @param array $config
     * @throws \Exception
     */
    protected function executeAction(array $config): void
    {
        try {
            $callback = $this->processElementFunction($config);

            $structure = $this->register->factory(Structure::class, [$config['path'], $config['params']['recursive']]);
            $structure->getReadDirectory();
            $structure->processSplObjects($callback);
        } catch (RegisterException $exception) {
            throw new \Exception('RegisterException: ' . $exception->getMessage());
        } catch (\Throwable $exception) {
            dump($exception);
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
            $actionClass = 'ToolsCli\Tools\System\CleanerAction\\' . \strtoupper($config['action']);
        }

        /** @var \ToolsCli\Tools\System\CleanerAction\Action $action */
        $action = $this->register->factory($actionClass, [$config['rules'], $this->blueStyle]);

        return $action->getCallback();
    }

    /**
     * @throws \RuntimeException
     */
    protected function readConfig(): void
    {
        try {
            $baseConfig = file_get_contents(__DIR__ . '/../../../etc/cleaner.json');
            $this->config = \json_decode($baseConfig, true)['list'];
            //@todo add read from main /etc dir
        } catch (\Throwable $exception) {
            throw new \RuntimeException($exception);
        }
    }
}
