<?php

namespace ToolsCli\Console;

use Symfony\Component\Console\Command\Command as BaseCommand;

class Command extends BaseCommand
{
    /**
     * @var Alias
     */
    protected Alias $alias;

    /**
     * @var string
     */
    protected $commandName;

    /**
     * @var array
     */
    protected $config;

    /**
     * @param string $name
     * @param $alias Alias
     */
    public function __construct(string $name, Alias $alias)
    {
        $this->alias = $alias;
        $this->config = $this->readConfig('config');

        parent::__construct($name);
    }

    /**
     * @return null|string
     */
    protected function checkAlias(): ?string
    {
        if (\is_null($this->alias)) {
            return null;
        }

        return $this->alias->getAlias($this->commandName);
    }

    /**
     * @return null|string
     */
    protected function getAlias(): ?string
    {
        $alias = '';

        $checkAlias = $this->checkAlias();
        if ($checkAlias) {
            $alias = '(<comment>' . $checkAlias . '</comment>) ';
        }

        return $alias;
    }

    /**
     * @param string $configJsonName
     * @return array
     */
    protected function readConfig(string $configJsonName): array
    {
        $configPath = "/etc/toolscli/$configJsonName.json";
        $varConfigName = \getenv('TOOLS_CLI_CONFIG_' . $configJsonName);

        if ($varConfigName) {
            $configPath = $varConfigName;
        }

        try {
            $baseConfig = \file_get_contents($configPath);
            return \json_decode($baseConfig, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException($exception);
        }
    }
}
