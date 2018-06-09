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

class DuplicatedFiles extends Command
{
    /**
     * @var Register
     */
    protected $register;

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
     */
    protected function execute(InputInterface $input, OutputInterface $output) : void
    {
        /** @var Style $blueStyle */
        $formatter = $this->register->factory(FormatterHelper::class);
        $blueStyle = $this->register->factory(Style::class, [$input, $output, $formatter]);
        $hashes = [];
        $names = [];
        $duplicatedFiles = 0;
        $duplicatedFilesSize = 0;
        $deleteCounter = 0;
        $deleteSizeCounter = 0;

        if ($input->getOption('interactive')) {
            $multiselect = (new MultiSelect($blueStyle))->toggleShowInfo(false);
        }

        //echo reading dir
        $list = Fs::readDirectory($input->getArgument('source'), true);
        $fileList = Fs::returnPaths($list)['file'];
        $allFiles = \count($fileList);
        $blueStyle->writeln("All files to check: $allFiles");
        $blueStyle->newLine();

        //echo reading files
        foreach ($fileList as $file) {
            if ($input->getOption('skip-empty') && filesize($file) === 0) {
                continue;
            }

            if ($input->getOption('check-by-name')) {
                $fileInfo = new \SplFileInfo($file);
                $name = $fileInfo->getFilename();

                $names[$file] = $name;
            } else {
                $hash = hash_file('sha3-256', $file);

                $hashes[$hash][] = $file;
            }
        }

        if ($input->getOption('check-by-name')) {
            //echo checking names
            foreach ($names as $path => $fileName) {
                unset($names[$path]);

                foreach ($names as $verifiedPath => $toVerified) {
                    $val = 0;
                    similar_text($fileName, $toVerified, $val);

                    if ($val >= (int)$input->getOption('check-by-name')) {
                        if (!($hashes[$fileName] ?? false)) {
                            $hashes[$fileName][] = $path;
                        }

                        $hashes[$fileName][] = $verifiedPath;
                    }
                }
            }
        }

        //echo checking files
        foreach ($hashes as $hash) {
            if (\count($hash) > 1) {
                $blueStyle->writeln('Duplications:');

                if ($input->getOption('interactive')) {
                    $hashWithSize = [];

                    foreach ($hash as $file) {
                        $duplicatedFiles++;
                        $size = filesize($file);
                        $duplicatedFilesSize += $size;

                        $formattedSize = Formats::dataSize($size);
                        $hashWithSize[] = "$file (<info>$formattedSize</>)";
                    }

                    //@todo show deleted file size
                    $selected = $multiselect->renderMultiSelect($hashWithSize);

                    if ($selected) {
                        foreach (array_keys($selected) as $idToDelete) {
                            //delete process
                            $deleteSizeCounter += filesize($hash[$idToDelete]);
                            $blueStyle->warningMessage('Removing: ' . $hash[$idToDelete]);
                            $out = Fs::delete($hash[$idToDelete]);

                            if (reset($out)) {
                                $blueStyle->okMessage('Removed success: ' . $hash[$idToDelete]);
                                $deleteCounter++;
                            } else {
                                $blueStyle->errorMessage('Removed fail: ' . $hash[$idToDelete]);
                            }
                        }
                    }
                } else {
                    foreach ($hash as $file) {
                        $duplicatedFiles++;
                        $size = filesize($file);
                        $duplicatedFilesSize += $size;

                        $formattedSize = Formats::dataSize($size);
                        $blueStyle->writeln("$file ($formattedSize)");
                    }
                }

                $blueStyle->newLine();
            }
        }

        if ($input->getOption('interactive')) {
            $blueStyle->writeln('Deleted files: ' . $deleteCounter);
            $blueStyle->writeln('Deleted files size: ' . Formats::dataSize($deleteSizeCounter));
            $blueStyle->newLine();
        }

        $blueStyle->writeln('Duplicated files: ' . $duplicatedFiles);
        $blueStyle->writeln('Duplicated files size: ' . Formats::dataSize($duplicatedFilesSize));
        $blueStyle->newLine();
        
        
        
        
        
        //set file in array with size, if file with the same size is detected, then calculate hash of that files and check hash
        //set file path & size in array, size as index, if index exists calculate hashes and add files into array
        //in seccond iteration check hashes and skip single files
        
        
        //sha2; sha-3
        //sha3-256, sha384, sha512, sha3-384
    }
}
