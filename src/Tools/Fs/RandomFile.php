#!/usr/bin/env php
<?php

new RandomFile($argv);

class RandomFile
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * randomFile constructor.
     *
     * @param array $args
     * @throws ErrorException
     * @throws Exception
     */
    public function __construct(array $args)
    {
        $allFiles = [];
        $storedFiles = [];

        $config = $args[2] ?? './etc/config.json';

        if (!file_exists($config)) {
            throw new ErrorException('Missing configuration file: ' . $config);
        }

        $configData = file_get_contents($config);
        $this->config = json_decode($configData, true);

        if (!file_exists($this->config['config']['storage'])) {
            throw new ErrorException('Missing storage file: ' . $this->config['config']['storage']);
        }

        $group = $args[1] ?? key($this->config['directories']);

        foreach ($this->config['directories'][$group] as $directory) {
            $paths = self::returnPaths(self::readDirectory($directory, true));
            $allFiles += $paths['file'];
        }

        $generated = file_get_contents($this->config['config']['storage']);
        $storage = json_decode($generated, true);

        foreach ($storage as $files) {
            $storedFiles[] = $files['path'];
        }

        $cleared = array_values(array_diff($allFiles, $storedFiles));

        if ($this->getImportantFile()) {
            $importantList = [];
            $prefix = $this->config['config']['importantFilePrefix'];

            foreach ($cleared as $file) {
                $fileInfo = new \SplFileInfo($file);
                $isImportant = preg_match("/^$prefix.*/", $fileInfo->getFilename());

                if ($isImportant) {
                    $importantList[] = $file;
                }
            }

            if (!empty($importantList)) {
                $cleared = $importantList;
            }
        }

        $numberOfFiles = count($cleared);
        $rand = random_int(1, $numberOfFiles);

        $randFile = $this->escape($cleared[$rand -1]);

        $storage[] = [
            'date' =>date('d-m-Y H:i:s'),
            'path' => $randFile,
        ];

        $newStorage = json_encode($storage, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        file_put_contents($this->config['config']['storage'], $newStorage);

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
     * @throws Exception
     */
    protected function getImportantFile() : bool
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
     * @return array|null
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

        $iterator = new DirectoryIterator($path);

        /** @var DirectoryIterator $element */
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
                /** @var DirectoryIterator $fileInfo */
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
