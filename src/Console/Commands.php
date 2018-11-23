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
        $fileList = glob(__DIR__ . '/../../src/Tools/*/*Tool.php');

        foreach ($fileList as $commandFile) {
            $namespace = $this->resolveToolNamespace($commandFile);

            $list[$namespace] = $this->registerCommandTool($namespace);
        }

        return $list;
    }

    /**
     * @param string $path
     * @return string
     */
    protected function resolveToolNamespace(string $path) : string
    {
        $gettingClass = false;
        $gettingNamespace = false;
        $namespace = '';
        $class = '';
        $contents = file_get_contents($path);

        foreach (token_get_all($contents) as $token) {
            if (\is_array($token) && $token[0] === T_NAMESPACE) {
                $gettingNamespace = true;
            }

            if (\is_array($token) && $token[0] === T_CLASS) {
                $gettingClass = true;
            }

            $namespace = $this->getNamespaceToken($token, $namespace, $gettingNamespace);
            $class = $this->getClassToken($token, $class, $gettingClass);

            if ($class) {
                break;
            }
        }

        return $namespace ? $namespace . '\\' . $class : $class;
    }

    /**
     * @param array|string $token
     * @param string $namespace
     * @param bool $gettingNamespace
     * @return string
     */
    protected function getNamespaceToken($token, string $namespace, bool &$gettingNamespace) : string
    {
        if ($gettingNamespace === true) {
            if (\is_array($token) && \in_array($token[0], [T_STRING, T_NS_SEPARATOR], true)) {
                $namespace .= $token[1];

            } elseif ($token === ';') {
                $gettingNamespace = false;

            }
        }

        return $namespace;
    }

    /**
     * @param array|string $token
     * @param string $class
     * @param bool $gettingClass
     * @return string
     */
    protected function getClassToken($token, string $class, bool $gettingClass) : string
    {
        if ($gettingClass === true && \is_array($token) && $token[0] === T_STRING) {
            $class = $token[1];
        }

        return $class;
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
