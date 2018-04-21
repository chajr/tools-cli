<?php

namespace ToolsCli\Console;

use Symfony\Component\Console\Command\Command as BaseCommand;

class Command extends BaseCommand
{
    /**
     * @var Alias
     */
    protected $alias;

    /**
     * @var string
     */
    protected $commandName;

    /**
     * @param string $name
     * @param $alias Alias
     */
    public function __construct(string $name, Alias $alias)
    {
        $this->alias = $alias;

        parent::__construct($name);
    }

    /**
     * @return null|string
     */
    protected function checkAlias() : ?string
    {
        if (\is_null($this->alias)) {
            return null;
        }

        return $this->alias->getAlias($this->commandName);
    }

    /**
     * @return null|string
     */
    protected function getAlias() : ?string
    {
        $alias = '';

        $checkAlias = $this->checkAlias();
        if ($checkAlias) {
            $alias = '(<comment>' . $checkAlias . '</comment>) ';
        }

        return $alias;
    }
}
