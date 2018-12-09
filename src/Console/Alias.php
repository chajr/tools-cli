<?php

namespace ToolsCli\Console;

use Symfony\Component\Console\Application;

class Alias
{
    /**
     * @var string
     */
    protected $alias = '';

    /**
     * @var array
     */
    protected $aliases = [];

    /**
     * @param array $args
     */
    public function __construct(array &$args)
    {
        if (!($args[1] ?? false)) {
            throw new \DomainException('Missing command. Type "tools-cli list" to get list of commands');
        }

        $aliases = $this->loadAliases();
        $this->aliases = array_flip($aliases);

        if (\array_key_exists($args[1], $aliases)) {
            $this->alias = $aliases[$args[1]];
            unset($args[1], $_SERVER['argv'][1]);
        }
    }

    /**
     * @return string
     */
    public function getAliases() : string
    {
        return $this->alias;
    }

    /**
     * @param string $commandName
     * @return string|null
     */
    public function getAlias(string $commandName) : ?string
    {
        return $this->aliases[$commandName] ?? null;
    }

    /**
     * @param Application $application
     * @param Commands $command
     */
    public function setCommand(Application $application, Commands $command) : void
    {
        if ($this->alias !== '') {
            $aliasCommand = $application->find($this->alias);
            $application->setDefaultCommand($aliasCommand->getName(), true);
        } else {
            $application->setDefaultCommand($command->get(DefaultCommand::class)->getName());
        }
    }

    /**
     * @return array
     */
    protected function loadAliases() : array
    {
        return parse_ini_file(__DIR__ . '/../../etc/alias.ini');
    }
}
