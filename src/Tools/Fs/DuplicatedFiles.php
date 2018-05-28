<?php

namespace ToolsCli\Tools\Fs;

use Symfony\Component\Console\{
    Input\InputInterface,
    Input\InputArgument,
    Output\OutputInterface,
};
use ToolsCli\Console\Command;
use BlueFilesystem\Fs;
use BlueData\Calculation\Math;
use BlueConsole\MultiSelect;

class DuplicatedFiles extends Command
{
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
        
        

        //format filesize
        
//
        //inverse mode (default select to delete, in inverse select to keep)
        //delete and create symbolic link
        

        //skipdir??
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $hashes = [];
        $names = [];
        $duplicatedFiles = 0;
        $duplicatedFilesSize = 0;

        if ($input->getOption('interactive')) {
            $style = new \Symfony\Component\Console\Style\SymfonyStyle($input, $output);
            $multiselect = (new MultiSelect($style))->toggleShowInfo(false);
        }

        //echo reading dir
        $list = Fs::readDirectory($input->getArgument('source'), true);
        $fileList = Fs::returnPaths($list)['file'];
        $allFiles = count($fileList);
        $output->writeln("All files to check: $allFiles");
        $output->writeln('');

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
            if (count($hash) > 1) {
                $output->writeln("Duplications:");

                if ($input->getOption('interactive')) {
                    $selected = $multiselect->renderMultiSelect($hash);

                    if ($selected) {
                        foreach (array_keys($selected) as $idToDelete) {
                            //delete process
                            $output->writeln('Removing: ' . $hash[$idToDelete]);
                            $out = Fs::delete($hash[$idToDelete]);

                            if (reset($out)) {
                                $output->writeln('Removed success: ' . $hash[$idToDelete]);
                            } else {
                                $output->writeln('Removed fail: ' . $hash[$idToDelete]);
                            }
                        }
                    }

                    foreach ($hash as $file) {
                        $duplicatedFiles++;
                        $size = filesize($file);
                        $duplicatedFilesSize += $size;
                    }
                } else {
                    foreach ($hash as $file) {
                        $duplicatedFiles++;
                        $size = filesize($file);
                        $duplicatedFilesSize += $size;
                        $output->writeln("$file ($size)");
                    }
                }

                $output->writeln('');
            }
        }

        $output->writeln('Duplicated files: ' . $duplicatedFiles);
        $output->writeln('Duplicated files size: ' . $duplicatedFilesSize);
        $output->writeln('');
        
        
        
        
        
        //progress bar (in case of full php without fdupes)
        //set file in array with size, if file with the same size is detected, then calculate hash of that files and check hash
        //set file path & size in array, size as index, if index exists calculate hashes and add files into array
        //in seccond iteration check hashes and skip single files
        
        
        //sha2; sha-3
        //sha3-256, sha384, sha512, sha3-384
    }
}
