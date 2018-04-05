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
     * @param array $args
     */
    public function __construct(array &$args)
    {
        $aliases = $this->loadAliases();

        if (\array_key_exists($args[1], $aliases)) {
            $this->alias = $aliases[$args[1]];
            unset($args[1], $_SERVER['argv'][1]);
        }
    }

    /**
     * @return string
     */
    public function getAlias() : string
    {
        return $this->alias;
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
        return parse_ini_file('etc/alias.ini');
    }
}
