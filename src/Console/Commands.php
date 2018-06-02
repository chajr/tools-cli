<?php

namespace ToolsCli\Console;

use Symfony\Component\Console\Command\Command;
use BlueContainer\Container;
use BlueRegister\Register;

class Commands extends Container
{
    /**
     * @var Register
     */
    protected $register;

    /**
     * @var Log
     */
    protected $log;

    /**
     * @var Alias
     */
    protected $alias;

    /**
     * @var Event
     */
    protected $event;

    public function __construct(Alias $alias)
    {
        //read configuration
//        $this->log = new Log([]);
//        $this->event = new Event([]);
        //create register (bootstrap function)
        $this->alias = $alias;
        $this->register = new Register([]);

        parent::__construct(['data' => $this->readAllCommandTools()]);

        $this->set(DefaultCommand::class, $this->registerCommandTool(DefaultCommand::class));

        //set default command
//        $this->set('default_name', 'helper');
    }

    /**
     * @return array
     * @todo read tools commands from vendor (recognize by special namespace)
     */
    protected function readAllCommandTools() : array
    {
        $list = [];
        $fileList = glob('src/Tools/*/*.php');

        foreach ($fileList as $commandFile) {
            $namespace = str_replace(
                ['/', 'src', '.php'],
                ['\\', 'ToolsCli', ''],
                $commandFile
            );

            $list[$namespace] = $this->registerCommandTool($namespace);
        }

        return $list;
    }

    /**
     * @param string $namespace
     * @return Command
     */
    protected function registerCommandTool(string $namespace) : Command
    {
        return $this->register->factory(
            $namespace,
            [$namespace, $this->alias, $this->register]
        );
    }
}
