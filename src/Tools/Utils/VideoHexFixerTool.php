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

        $source = $input->getArgument('source');
        $fileErr = fopen($source, 'rb');
        $content = fread($fileErr, filesize($source));
        $content = bin2hex(fread($fileErr, filesize($source)));
        var_dump(strpos($content, '00'));
        
        
    }
}
