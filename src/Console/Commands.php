<?php

namespace ToolsCli\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use BlueContainer\Container;
use BlueRegister\Register;
use BlueRegister\RegisterException;
use BlueCache\SimpleCache;

class Commands extends Container
{
    public const MAIN_DIR = __DIR__ . '/../../';

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

    /**
     * @var SimpleCache
     */
    protected $cache;

    /**
     * @param Alias $alias
     */
    public function __construct(Alias $alias)
    {
        //@todo read configuration
        //@todo create bootstrap function
        $this->alias = $alias;
        $this->register = new Register([]);

        try {
            $this->cache = $this->register->factory(
                SimpleCache::class,
                [['storage_directory' => self::MAIN_DIR . 'var/cache']]
            );
//        $this->event = new Event([]);
//        $this->log = new Log([]);
        } catch (RegisterException $exception) {
            throw new \RuntimeException('Unable to register class. ' . $exception->getMessage());
        }

        try {
            parent::__construct(['data' => $this->readAllCommandTools()]);
        } catch (\Exception $exception) {
            dump($exception->getMessage());
        }

        $this->set(DefaultCommand::class, $this->registerCommandTool(DefaultCommand::class));

        //@todo set default command
//        $this->set('default_name', 'helper');
    }

    /**
     * @return array
     * @todo read tools commands from vendor (recognize by special namespace)
     * @throws \BlueCache\CacheException
     * @throws \Exception
     */
    protected function readAllCommandTools() : array
    {
        $list = [];
        $namespaces = [];

        if ($this->cache->has('tools')) {
            $namespaces = $this->cache->get('tools');
        } else {
            $namespaces['file_list'] = glob(self::MAIN_DIR . 'src/Tools/*/*Tool.php');

            foreach ($namespaces['file_list'] as $commandFile) {
                $namespaces['list'][$commandFile] = $this->resolveToolNamespace($commandFile);
            }

            $this->cache->set('tools', $namespaces, new \DateInterval('P0Y0M1DT0H0M0S'));
        }

        foreach ($namespaces['file_list'] as $commandFile) {
            $namespace = $namespaces['list'][$commandFile];
            $object = $this->registerCommandTool($namespace);

            if ($object !== null) {
                $list[$namespace] = $object;
            }
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
            $isArray = \is_array($token);
 
            if ($isArray && $token[0] === T_NAMESPACE) {
                $gettingNamespace = true;
            }

            if ($isArray && $token[0] === T_CLASS) {
                $gettingClass = true;
            }

            $namespace = $this->getNamespaceToken($token, $namespace, $gettingNamespace, $isArray);
            if ($isArray) {
                $class = $this->getClassToken($token, $class, $gettingClass);
            }

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
     * @param bool $isArray
     * @return string
     */
    protected function getNamespaceToken($token, string $namespace, bool &$gettingNamespace, bool $isArray) : string
    {
        if ($gettingNamespace === true) {
            if ($isArray && \in_array($token[0], [T_STRING, T_NS_SEPARATOR], true)) {
                $namespace .= $token[1];
            } elseif ($token === ';') {
                $gettingNamespace = false;
            }
        }

        return $namespace;
    }

    /**
     * @param array $token
     * @param string $class
     * @param bool $gettingClass
     * @return string
     */
    protected function getClassToken(array $token, string $class, bool $gettingClass) : string
    {
        if ($gettingClass === true && $token[0] === T_STRING) {
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
            (new ConsoleOutput)->writeln('<error>' . $exception->getMessage() . '</error>');
        }

        return null;
    }
}
