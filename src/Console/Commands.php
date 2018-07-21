<?php

namespace ToolsCli\Console;

use Symfony\Component\Console\Command\Command;
use BlueContainer\Container;
use BlueRegister\Register;
use \BlueRegister\RegisterException;
use Symfony\Component\Console\Output\ConsoleOutput as Output;
use Symfony\Component\Console\Formatter\OutputFormatter;

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
        //@todo read configuration
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
        $fileList = glob('src/Tools/*/*Tool.php');

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
     * @return Command|null
     */
    protected function registerCommandTool(string $namespace) : ?Command
    {
        try {
            return $this->register->factory(
                $namespace,
                [$namespace, $this->alias, $this->register]
            );
        } catch (RegisterException $exception) {
            $output = new Output;
            $output->setFormatter(new OutputFormatter);
            //@todo use symfony style but without input

            $output->writeln($exception->getMessage());
        }

        return null;
    }
}
