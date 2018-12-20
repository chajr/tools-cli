<?php

namespace ToolsCli\Tools\Fs;

use Symfony\Component\Console\{
    Input\InputInterface,
    Input\InputArgument,
    Output\OutputInterface,
    Helper\FormatterHelper,
    Helper\ProgressBar,
};
use ToolsCli\Console\{
    Command,
    Alias,
};
use ToolsCli\Tools\Fs\Duplicated\Strategy;
use BlueFilesystem\Fs;
use BlueData\Data\Formats;
use BlueRegister\{
    Register, RegisterException
};
use BlueConsole\Style;
use ToolsCli\Tools\Fs\Duplicated\Name;

class CopyAndReplaceExists extends Command
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

    /**
     * @var string
     */
    protected $messageFormat = ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%';

    /**
     * @var ProgressBar
     */
    protected $progressBar;

    protected $deleteCounter = 0;
    protected $deleteSizeCounter = 0;
    protected $copiedCounter = 0;

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
        $this->setName('fs:copy')
            ->setDescription('Search files duplication and make some action on it.')
            ->setHelp('');

        $this->addArgument(
            'destination',
            InputArgument::REQUIRED,
            'directory to copy files'
        );

        $this->addArgument(
            'source',
            InputArgument::REQUIRED,
            'directory from files will be copied'
        );

        $this->addOption(
            'progress-info',
            'p',
            null,
            'Show massage on progress bar (like filename during hashing)'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;

        try {
            $this->formatter = $this->register->factory(FormatterHelper::class);
            $this->blueStyle = $this->register->factory(Style::class, [$input, $output, $this->formatter]);
            $this->progressBar = $this->register->factory(ProgressBar::class, [$output]);
        } catch (RegisterException $exception) {
            throw new \RuntimeException('RegisterException: ' . $exception->getMessage());
        }

        $this->progressBar->setFormat(
            $this->messageFormat . ($this->input->getOption('progress-info') ? '%message%' : '')
        );

        $this->blueStyle->writeln('Reading directory.');
        $list = Fs::readDirectory($input->getArgument('source'), true);
        $fileList = Fs::returnPaths($list)['file'];
        $allFiles = \count($fileList);
        $this->blueStyle->writeln("All files to copy: $allFiles");
        $this->blueStyle->newLine();
        
        foreach ($fileList as $file) {
            //check that destination file exists
            //if not copy normally
            //if exists check both files hashes
            //if hashes are the same skip file
            //if different copy with change name
        }
        
        
        
        
        
        
        
        
        
        
        
        

//        $this->blueStyle->writeln('Building file hash list.');
//        $list = $this->buildList($fileList);
//
//        $this->blueStyle->newLine();
//        $this->blueStyle->writeln('Compare files.');
//        $this->duplicationCheckStrategy($list);
//
//        if ($input->getOption('interactive')) {
//            $this->blueStyle->writeln('Deleted files: ' . $this->deleteCounter);
//            $this->blueStyle->writeln('Deleted files size: ' . Formats::dataSize($this->deleteSizeCounter));
//            $this->blueStyle->newLine();
//        }
//
//        $this->blueStyle->writeln('Duplicated files: ' . $this->duplicatedFiles);
//        $this->blueStyle->writeln('Duplicated files size: ' . Formats::dataSize($this->duplicatedFilesSize));
//        $this->blueStyle->newLine();
    }

    /**
     * @param array $fileList
     * @return array
     */
//    protected function buildList(array $fileList): array
//    {
//        $hashes = [];
//        $names  = [];
//        $this->progressBar->start(\count($fileList));
//
//        foreach ($fileList as $file) {
//            $this->progressBar->advance();
//
//            if ($this->input->getOption('progress-info')) {
//                $this->progressBar->setMessage($file);
//            }
//
//            if ($this->input->getOption('skip-empty') && filesize($file) === 0) {
//                continue;
//            }
//
//            if ($this->input->getOption('check-by-name')) {
//                $fileInfo = new \SplFileInfo($file);
//                $name = $fileInfo->getFilename();
//
//                $names[$file] = $name;
//            } else {
//                $hash = hash_file('sha3-256', $file);
//
//                $hashes[$hash][] = $file;
//            }
//        }
//
//        $this->progressBar->finish();
//
//        return [$names, $hashes];
//    }
//
//    /**
//     * @param array $list
//     * @throws \Exception
//     */
//    protected function duplicationCheckStrategy(array $list) : void
//    {
//        [$names, $hashes] = $list;
//        $name = 'Interactive';
//
//        if (!$this->input->getOption('interactive')) {
//            $name = 'No' . $name;
//        }
//
//        try {
//            if ($this->input->getOption('check-by-name')) {
//                /** @var \ToolsCli\Tools\Fs\Duplicated\Name $checkByName */
//                $checkByName = $this->register->factory(Name::class);
//                $hashes = $checkByName->checkByName($names, $hashes, $this->input->getOption('check-by-name'));
//            }
//
//            /** @var Strategy $strategy */
//            $strategy = $this->register->factory('ToolsCli\Tools\Fs\Duplicated\\' . $name, [$this]);
//        } catch (RegisterException $exception) {
//            throw new \Exception('RegisterException: ' . $exception->getMessage());
//        }
//
//        foreach ($hashes as $hash) {
//            if (\count($hash) > 1) {
//                $strategy->checkByHash($hash);
//            }
//        }
//
//        [$this->duplicatedFiles, $this->duplicatedFilesSize, $this->deleteCounter, $this->deleteSizeCounter]
//            = $strategy->returnCounters();
//    }
}
