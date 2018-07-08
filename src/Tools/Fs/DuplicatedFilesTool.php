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
use BlueFilesystem\Fs;
use BlueData\Data\Formats;
use BlueConsole\MultiSelect;
use BlueRegister\Register;
use BlueConsole\Style;

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
        $this->register= $register;
        parent::__construct($name, $alias);
    }

    protected function configure() : void
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
            'skip check if file is empty'
        );

//        $this->addOption(
//            'hash',
//            '',
//            null,
//            'display files hashes ??? only duplicated, all files??'
//        );
    }

    /**
     * 3. refactor
     * 4. progress bar, skip dir, create link after delete original file, inverse selection, show hash
     * 5. first check by filesize
     */

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     * @throws \BlueRegister\RegisterException
     */
    protected function execute(InputInterface $input, OutputInterface $output) : void
    {
        $this->input = $input;
        $this->output = $output;
        $this->formatter = $this->register->factory(FormatterHelper::class);
        /** @var Style $blueStyle */
        $this->blueStyle = $this->register->factory(Style::class, [$input, $output, $this->formatter]);

        //echo reading dir
        $list = Fs::readDirectory($input->getArgument('source'), true);
        $fileList = Fs::returnPaths($list)['file'];
        $allFiles = \count($fileList);
        $this->blueStyle->writeln("All files to check: $allFiles");
        $this->blueStyle->newLine();

        //echo reading files
        [$names, $hashes] = $this->buildList($fileList);
        $hashes = $this->checkByName($names, $hashes);

        //echo checking files
        $this->checkByHash($hashes);

        if ($input->getOption('interactive')) {
            $this->blueStyle->writeln('Deleted files: ' . $this->deleteCounter);
            $this->blueStyle->writeln('Deleted files size: ' . Formats::dataSize($this->deleteSizeCounter));
            $this->blueStyle->newLine();
        }

        $this->blueStyle->writeln('Duplicated files: ' . $this->duplicatedFiles);
        $this->blueStyle->writeln('Duplicated files size: ' . Formats::dataSize($this->duplicatedFilesSize));
        $this->blueStyle->newLine();
        
        
        
        
        
        //set file in array with size, if file with the same size is detected, then calculate hash of that files and check hash
        //set file path & size in array, size as index, if index exists calculate hashes and add files into array
        //in seccond iteration check hashes and skip single files
        
        
        //sha2; sha-3
        //sha3-256, sha384, sha512, sha3-384
    }

    /**
     * @param array $fileList
     * @return array
     */
    protected function buildList(array $fileList) : array
    {
        $hashes = [];
        $names = [];

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
     * @param array $names
     * @param array $hashes
     * @return mixed
     */
    protected function checkByName(array $names, array $hashes) : array
    {
        foreach ($names as $path => $fileName) {
            unset($names[$path]);

            foreach ($names as $verifiedPath => $toVerified) {
                $val = 0;
                similar_text($fileName, $toVerified, $val);

                if ($val >= (int)$this->input->getOption('check-by-name')) {
                    if (!($hashes[$fileName] ?? false)) {
                        $hashes[$fileName][] = $path;
                    }

                    $hashes[$fileName][] = $verifiedPath;
                }
            }
        }

        return $hashes;
    }

    /**
     * @param array $hashes
     * @return $this
     */
    protected function checkByHash(array $hashes) : self
    {
        if ($this->input->getOption('interactive')) {
            $multiselect = (new MultiSelect($this->blueStyle))->toggleShowInfo(false);
        }

        foreach ($hashes as $hash) {
            if (\count($hash) > 1) {
                $this->blueStyle->writeln('Duplications:');

                if ($this->input->getOption('interactive')) {
                    $this->interactive(
                        $hash,
                        $multiselect
                    );
                } else {
                    foreach ($hash as $file) {
                        $this->duplicatedFiles++;
                        $size = filesize($file);
                        $this->duplicatedFilesSize += $size;

                        $formattedSize = Formats::dataSize($size);
                        $this->blueStyle->writeln("$file ($formattedSize)");
                    }
                }

                $this->blueStyle->newLine();
            }
        }

        return $this;
    }

    protected function duplicationStrategy()
    {
        //interactive
        //elese

    }

    /**
     * @param array $hash
     * @param MultiSelect $multiselect
     * @return $this
     */
    protected function interactive(
        array $hash,
        MultiSelect $multiselect
    ) : self {
        $hashWithSize = [];

        foreach ($hash as $file) {
            $this->duplicatedFiles++;
            $size = filesize($file);
            $this->duplicatedFilesSize += $size;

            $formattedSize = Formats::dataSize($size);
            $hashWithSize[] = "$file (<info>$formattedSize</>)";
        }

        //@todo show deleted file size
        $selected = $multiselect->renderMultiSelect($hashWithSize);

        if ($selected) {
            foreach (array_keys($selected) as $idToDelete) {
                //delete process
                $this->deleteSizeCounter += filesize($hash[$idToDelete]);
                $this->blueStyle->warningMessage('Removing: ' . $hash[$idToDelete]);
                $out = Fs::delete($hash[$idToDelete]);

                if (reset($out)) {
                    $this->blueStyle->okMessage('Removed success: ' . $hash[$idToDelete]);
                    $this->deleteCounter++;
                } else {
                    $this->blueStyle->errorMessage('Removed fail: ' . $hash[$idToDelete]);
                }
            }
        }

        return $this;
    }
}
