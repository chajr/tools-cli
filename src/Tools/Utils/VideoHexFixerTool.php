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
        $crBin = hex2bin('0d');
        $crBinReplace = hex2bin('0d00');
        $nlBin = hex2bin('0a');
        $nlBinReplace = hex2bin('0d0a');

        $nlPos = [];
        $crPos = [];
        
        $data = '';
        $size = filesize($source);
        echo "process file\n";
        for ($i = 1; $i <= $size; $i++) {
            $char = fgetc($fileErr);

            if ($char === $crBin) {
                $crCharsCount++;
                $crPos[] = $i -1;
            }
            if ($char === $nlBin) {
                $nlCharsCount++;
                $nlPos[] = $i -1;
            }

            $data .= $char;
        }

        $crReplaceCount = 0;
        for ($j = 0; $j < $crCharsCount; $j++) {
            $dataR = $data;
            $crReplaceCount2 = 0;
            for ($k = 0; $k < $crCharsCount; $k++) {
                if ($k & $crReplaceCount) {
                    $rPos = $crPos[$k] + $crReplaceCount2;
                    echo "replacement in: $j:$k - $rPos\n";
                    $dataR = substr_replace($dataR, $crBinReplace, $rPos, 1);
                }
                $crReplaceCount2++;
            }
            $crReplaceCount++;

            $fileOut = fopen($input->getArgument('destination') . "/test-$j.mp4", 'wb');
            fwrite($fileOut, $dataR);
        }

        echo "out\n\n";
        echo "size: $size\n";
        echo "nl count: $nlCharsCount\n";
        echo "cr count: $crCharsCount\n";
        echo "i: $i\n";
        echo "date len: " . strlen($data);
        echo PHP_EOL;
    }
}
