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

        //skip empty files
        //show hash
        //format filesize
        //check by similar names -ns -ns 50 - percent (int similar_text ( string $first , string $second [, float &$percent ] ))
        
//
        //inverse mode (default select to delete, in inverse select to keep)
        //delete and create symbolic link
        

        //skipdir??
        //generate html file with buttons to delete duplicated files
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
        //build list of all files and their paths (bluefs)
        //foreach on list and calculate hash
        
        $list = Fs::readDirectory($input->getArgument('source'), true);
        $fileList = Fs::returnPaths($list)['file'];

        //echo reading files
        foreach ($fileList as $file) {
            $hash = hash_file('sha3-256', $file);

            $hashes[$hash][] = $file;
        }

        //echo checking files
        foreach ($hashes as $hash) {
            if (count($hash) > 1) {
                $size = filesize(reset($hash));
                $output->writeln("Duplications ($size):");

                foreach ($hash as $file) {
                    $output->writeln($file);
                }

                $output->writeln('');
            }
        }
        
        
        
        //generate html file with possibility to delete file
        
        //fduper -r 
        
        
        //select 1    1,2,3    1-6   a
        
        //count files all, show size + deleted
        //progress bar (in case of full php without fdupes)
        //hash_file
        //hash_equals
        //set file in array with size, if file with the same size is detected, then calculate hash of that files and check hash
        //set file path & size in array, size as index, if index exists calculate hashes and add files into array
        //in seccond iteration check hashes and skip single files
        
        
        //sha2; sha-3
        //sha3-256, sha384, sha512, sha3-384
        
        
        
        //count all files after build tree (or find -type f) and progress bar of checking files
    }
}
