<?php

namespace ToolsCli\Tools\Fs;

use Symfony\Component\Console\{
    Command\Command,
    Input\InputInterface,
    Input\InputArgument,
    Output\OutputInterface,
};

class RandomFileTool extends Command
{
    /**
     * @var array
     */
    protected $config = [];

    protected function configure() : void
    {
        $this->setName('fs:random-file')
            ->setDescription('Return random file path.')
            ->setHelp('');

        $this->addArgument(
            'group',
            InputArgument::OPTIONAL,
            'directory group to get random file'
        );

        $this->addOption(
            'config',
            'c',
            InputArgument::OPTIONAL,
            'additional configuration for generation and storage results',
            '~/.config/tools-cli/etc/randomFile.json'
        );

        $this->addOption(
            'skip-storage',
            's',
            null,
            'don\'t save generated file in result file'
        );

        $this->addOption(
            'dir',
            'd',
            null,
            'force directory to get random file (force skip-storage option)'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     * @throws \ErrorException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $allFiles = [];
        $storedFiles = [];

        $config = $input->getOption('config');

        if (!file_exists($config)) {
            throw new \ErrorException('Missing configuration file: ' . $config);
        }

        $configData = file_get_contents($config);
        $this->config = json_decode($configData, true);

        if (!file_exists($this->config['config']['storage'])) {
            throw new \ErrorException('Missing storage file: ' . $this->config['config']['storage']);
        }

        $group = $input->getArgument('directory') ?? key($this->config['directories']);

        foreach ($this->config['directories'][$group] as $directory) {
            $paths = self::returnPaths(self::readDirectory($directory, true));
            $allFiles += $paths['file'];
        }

        if (!$input->getOption('skip-storage')) {
            $generated = file_get_contents($this->config['config']['storage']);
            $storage = json_decode($generated, true);

            foreach ($storage as $files) {
                $storedFiles[] = $files['path'];
            }

            $allFiles = array_values(array_diff($allFiles, $storedFiles));
        }

        if ($this->hasImportantFile()) {
            $importantList = [];
            $prefix = $this->config['config']['importantFilePrefix'];

            foreach ($allFiles as $file) {
                $fileInfo = new \SplFileInfo($file);
                $isImportant = preg_match("/^$prefix.*/", $fileInfo->getFilename());

                if ($isImportant) {
                    $importantList[] = $file;
                }
            }

            if (!empty($importantList)) {
                $allFiles = $importantList;
            }
        }

        $numberOfFiles = \count($allFiles);
        $rand = random_int(1, $numberOfFiles);

        $randFile = $this->escape($allFiles[$rand -1]);

        if (!$input->getOption('skip-storage')) {
            $storage[] = [
                'date' => date('d-m-Y H:i:s'),
                'path' => $randFile,
            ];

            $newStorage = json_encode($storage, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            file_put_contents($this->config['config']['storage'], $newStorage);
        }

        echo '"' . $randFile . '"';
    }

    /**
     * @param string $path
     * @return string
     */
    protected function escape(string $path) : string
    {
        return str_replace('!', '\!', $path);
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function hasImportantFile() : bool
    {
        $randCheck = $this->config['config']['importantFileCheckout'];

        for ($i = 0; $i < $randCheck; $i++) {
            $rand = random_int(0, 1);

            if ($rand === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * read directory content, (optionally all sub folders)
     *
     * @param string $path
     * @param boolean $whole
     * @return array
     * @example readDirectory('dir/some_dir')
     * @example readDirectory('dir/some_dir', TRUE)
     * @example readDirectory(); - read MAIN_PATH destination
     */
    public static function readDirectory(string $path, bool $whole = false) : array
    {
        $list = [];

        if (!self::exist($path)) {
            return $list;
        }

        $iterator = new \DirectoryIterator($path);

        /** @var \DirectoryIterator $element */
        foreach ($iterator as $element) {
            if ($element->isDot()) {
                continue;
            }

            if ($whole && $element->isDir()) {
                $list[$element->getRealPath()] = self::readDirectory(
                    $element->getRealPath(),
                    true
                );
            } else {
                $list[$element->getRealPath()] = $element->getFileInfo();
            }

        }

        return $list;
    }

    /**
     * transform array wit directory/files tree to list of paths grouped on files and directories
     *
     * @param array $array array to transform
     * @param boolean $reverse if TRUE revert array (required for deleting)
     * @internal param string $path base path for elements, if emty use paths from transformed structure
     * @return array array with path list for files and directories
     * @example returnPaths($array, '')
     * @example returnPaths($array, '', TRUE)
     * @example returnPaths($array, 'some_dir/dir', TRUE)
     */
    public static function returnPaths(array $array, bool $reverse = false) : array
    {
        if ($reverse) {
            $array = array_reverse($array);
        }

        $pathList = [];

        foreach ($array as $path => $fileInfo) {
            if (is_dir($path)) {
                $list = self::returnPaths((array)$fileInfo);

                foreach ($list as $element => $value) {
                    if ($element === 'file') {
                        foreach ($value as $file) {
                            $pathList['file'][] = $file;
                        }
                    }

                    if ($element === 'dir') {
                        foreach ($value as $dir) {
                            $pathList['dir'][] = $dir;
                        }
                    }

                }
                $pathList['dir'][] = $path;

            } else {
                /** @var \DirectoryIterator $fileInfo */
                $pathList['file'][] = $fileInfo->getRealPath();
            }
        }

        return $pathList;
    }

    /**
     * check that file exists
     *
     * @param string $path
     * @return boolean TRUE if exists, FALSE if not
     */
    public static function exist(string $path) : bool
    {
        return file_exists($path);
    }
}
