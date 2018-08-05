<?php

namespace ToolsCli\Tools\Fs;

use Symfony\Component\Console\{
    Input\InputInterface,
    Input\InputArgument,
    Output\OutputInterface,
    Helper\FormatterHelper,
};
use ToolsCli\Console\{
    Command,
    Alias,
};
use ToolsCli\Tools\Fs\Duplicated\Strategy;
use BlueFilesystem\Fs;
use BlueData\Data\Formats;
use BlueRegister\Register;
use BlueConsole\Style;
use ToolsCli\Tools\Fs\Duplicated\Name;

class DuplicatedFilesTool extends Command
{
    /**
     * @var Register
     */
    protected $register;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Style
     */
    protected $blueStyle;

    /**
     * @var FormatterHelper
     */
    protected $formatter;

    protected $deleteCounter = 0;
    protected $deleteSizeCounter = 0;
    protected $duplicatedFiles = 0;
    protected $duplicatedFilesSize = 0;

    /**
     * @param string $name
     * @param Alias $alias
     * @param Register $register
     */
    public function __construct(string $name, Alias $alias, Register $register)
    {
        $this->register = $register;
        parent::__construct($name, $alias);
    }

    /**
     * @return Style
     */
    public function getBlueStyle(): Style
    {
        return $this->blueStyle;
    }

    protected function configure(): void
    {
        $this->setName('fs:duplicated')
            ->setDescription('Search files duplication and make some action on it.')
            ->setHelp('');

        $this->addArgument(
            'source',
            InputArgument::REQUIRED,
            'source files to check'
        );

        $this->addOption(
            'interactive',
            'i',
            null,
            'show multi-checkbox with duplicated files, selected will be deleted'
        );

        $this->addOption(
            'skip-empty',
            's',
            null,
            'skip check if file is empty'
        );

        $this->addOption(
            'check-by-name',
            'c',
            InputArgument::OPTIONAL,
            'compare files using their file names. As arg give comparation parameter'
        );

//        $this->addOption(
//            'hash',
//            '',
//            null,
//            'display files hashes ??? only duplicated, all files??'
//        );
    }

    /**
     * 4. progress bar, skip dir, create link after delete original file, inverse selection, show hash
     * 5. first check by filesize
     * 6. multithread (calculate hashes in separate threads, compare in separate (full list and splited list)
     * 7. interactive delete list after comparation process
     * //set file in array with size, if file with the same size is detected, then calculate hash of that files and check hash
     * //set file path & size in array, size as index, if index exists calculate hashes and add files into array
     * //in seccond iteration check hashes and skip single files
     */

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     * @throws \BlueRegister\RegisterException
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;
        $this->formatter = $this->register->factory(FormatterHelper::class);
        /** @var Style $blueStyle */
        $this->blueStyle = $this->register->factory(Style::class, [$input, $output, $this->formatter]);

        $this->blueStyle->writeln('Reading directory.');
        $list = Fs::readDirectory($input->getArgument('source'), true);
        $fileList = Fs::returnPaths($list)['file'];
        $allFiles = \count($fileList);
        $this->blueStyle->writeln("All files to check: $allFiles");
        $this->blueStyle->newLine();

        $this->blueStyle->writeln('Building file hash list.');
        $list = $this->buildList($fileList);

        $this->blueStyle->writeln('Compare files.');
        $this->duplicationCheckStrategy($list);

        if ($input->getOption('interactive')) {
            $this->blueStyle->writeln('Deleted files: ' . $this->deleteCounter);
            $this->blueStyle->writeln('Deleted files size: ' . Formats::dataSize($this->deleteSizeCounter));
            $this->blueStyle->newLine();
        }

        $this->blueStyle->writeln('Duplicated files: ' . $this->duplicatedFiles);
        $this->blueStyle->writeln('Duplicated files size: ' . Formats::dataSize($this->duplicatedFilesSize));
        $this->blueStyle->newLine();
    }

    /**
     * @param array $fileList
     * @return array
     */
    protected function buildList(array $fileList): array
    {
        $hashes = [];
        $names  = [];

        foreach ($fileList as $file) {
            if ($this->input->getOption('skip-empty') && filesize($file) === 0) {
                continue;
            }

            if ($this->input->getOption('check-by-name')) {
                $fileInfo = new \SplFileInfo($file);
                $name = $fileInfo->getFilename();

                $names[$file] = $name;
            } else {
                $hash = hash_file('sha3-256', $file);

                $hashes[$hash][] = $file;
            }
        }

        return [$names, $hashes];
    }

    /**
     * @param array $list
     */
    protected function duplicationCheckStrategy(array $list) : void
    {
        [$names, $hashes] = $list;
        $name = 'Interactive';

        if (!$this->input->getOption('interactive')) {
            $name = 'No' . $name;
        }

        if ($this->input->getOption('check-by-name')) {
            /** @var \ToolsCli\Tools\Fs\Duplicated\Name $checkByName */
            $checkByName = $this->register->factory(Name::class);
            $hashes = $checkByName->checkByName($names, $hashes, $this->input->getOption('check-by-name'));
        }

        /** @var Strategy $strategy */
        $strategy = $this->register->factory('ToolsCli\Tools\Fs\Duplicated\\' . $name, [$this]);

        foreach ($hashes as $hash) {
            if (\count($hash) > 1) {
                  $strategy->checkByHash($hash);
            }
        }

        [$this->duplicatedFiles, $this->duplicatedFilesSize, $this->deleteCounter, $this->deleteSizeCounter]
            = $strategy->returnCounters();
    }
}
