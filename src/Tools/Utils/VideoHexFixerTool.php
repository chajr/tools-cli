<?php

namespace ToolsCli\Tools\Utils;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use BlueRegister\Register;
use ToolsCli\Console\Alias;

class VideoHexFixerTool extends Command
{
    public function __construct(string $name, Alias $alias, Register $register)
    {
        $this->register= $register;
        parent::__construct($name, $alias);
    }

    protected function configure() : void
    {
        $this->setName('utils:video-hex-fixer')
            ->setDescription('Fix hex changes after ASCII copy.')
            ->setHelp('');

        $this->addArgument(
            'source',
            InputArgument::REQUIRED,
            'source files to fix'
        );

        $this->addArgument(
            'destination',
            InputArgument::REQUIRED,
            'directory to copy restored files'
        );

        $this->addArgument(
            'temp',
            InputArgument::OPTIONAL,
            'temporary directory to store files to check',
            '/tmp'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //read video file$fileErr
        //search hex occurrence
        //count occurrences
        //prepare list of possibilities (binary)
        //convert binary to decimal
        //start decimal loop
        //check with loop number (binary) and decide to replace or leave hex
        //save file as temp
        //check that file is playable
        echo "read file\n";
        $source = $input->getArgument('source');
        $fileErr = fopen($source, 'rb');
//        $content = fread($fileErr, filesize($source));
//        var_dump(filesize($source));
//        $content = bin2hex(fread($fileErr, filesize($source)));
//        var_dump(strlen($content));
//        var_dump(bin2hex(fread($fileErr, 100)));
//        var_dump(substr_count($content, '0d'));
//        var_dump(substr_count($content, '0a'));
//        var_dump(strpos($content, '0d', 19207));
        
        
//        $counter = 0;
        $nlCharsCount = 0;
        $crCharsCount = 0;
        $crBin = hex2bin(0x0d);
        $nlBin = hex2bin(0x0a);

        $nlPos = [];
        $crPos = [];
        
        $data = '';
        $size = filesize($source);
        echo "process file\n";
        for ($i = 1; $i <= $size; $i++) {
            $charOk = fgetc($fileErr);

            if ($charOk === $crBin) {
                $crCharsCount++;
                $crPos[] = $i -1;
            }
            if ($charOk === $nlBin) {
                $nlCharsCount++;
                $nlPos[] = $i -1;
            }

            $data .= $charOk;
        }

        echo "out\n\n";
        var_dump($size);
        var_dump($nlCharsCount);
        var_dump($crCharsCount);
        var_dump($i);
        var_dump(strlen($data));
    }
}
