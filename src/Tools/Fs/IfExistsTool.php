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

class IfExistsTool extends Command
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
    protected $messageFormat = ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%';

    /**
     * @var ProgressBar
     */
    protected $progressBar;

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

    protected function configure(): void
    {
        $this->setName('fs:if-exists')
            ->setDescription(
                'Compare files in both directories and check if files from second exists in first.'
                . 'By default show files that don\'t exists'
            )
            ->setHelp('');

        $this->addArgument(
            'first',
            InputArgument::REQUIRED,
            'source files to check'
        );

        $this->addArgument(
            'second',
            InputArgument::REQUIRED,
            'source files to check'
        );

        $this->addOption(
            'remove',
            'R',
            null,
            'show multi-checkbox with duplicated files, selected will be deleted'
        );

        $this->addOption(
            'check-by-name',
            'c',
            InputArgument::OPTIONAL,
            'compare files using their file names. As arg give comparision parameter'
        );

        $this->addOption(
            'auto-remove',
            'd',
            null,
            ''
        );

        $this->addOption(
            'return-exists',
            'e',
            null,
            'Show files that are existing instead of not existing files.'
        );

        $this->addOption(
            'return-all',
            'a',
            null,
            'Show existing and not existing files.'
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
            throw new \Exception('RegisterException: ' . $exception->getMessage());
        }

        $this->progressBar->setFormat($this->messageFormat);

        $this->blueStyle->writeln('Reading directories.');
        $firstList = Fs::readDirectory($input->getArgument('first'), true);
        $secondList = Fs::readDirectory($input->getArgument('second'), true);
        $fileListFirst = Fs::returnPaths($firstList)['file'];
        $fileListSecond = Fs::returnPaths($secondList)['file'];

        $allFilesFirst = \count($fileListFirst);
        $allFilesSecond = \count($fileListSecond);
        $this->blueStyle->writeln("All files to check, first dir: $allFilesFirst, second dir: $allFilesSecond");
        $this->blueStyle->newLine();

        $this->blueStyle->writeln('Building file hash list.');

        $listFirst = $this->buildList($fileListFirst);
        $this->blueStyle->newLine();
        $listSecond = $this->buildList($fileListSecond);
        $this->blueStyle->newLine();

        $this->blueStyle->writeln('Compare files.');

        $firstDirHashes = array_keys($listFirst[1]);

        foreach ($listSecond[1] as $hash => $file) {
            $showExists = $input->getOption('return-exists');
            $showAll = $input->getOption('return-all');

            if (($showExists || $showAll) && \in_array($hash, $firstDirHashes, true)) {
                $this->blueStyle->warningMessage('Existing: ' . $listFirst[1][$hash] . ' -> ' . $file);
            } 

            if ((!$showExists || $showAll) && !\in_array($hash, $firstDirHashes, true)) {
                $this->blueStyle->errorMessage('Not exists: ' . $file);
            }
        }


//        $this->duplicationCheckStrategy($list);


//        $this->blueStyle->writeln('Duplicated files: ' . $this->duplicatedFiles);
//        $this->blueStyle->writeln('Duplicated files size: ' . Formats::dataSize($this->duplicatedFilesSize));
//        $this->blueStyle->newLine();
    }

    /**
     * @param array $fileList
     * @return array
     */
    protected function buildList(array $fileList): array
    {
        $hashes = [];
        $names  = [];
        $this->progressBar->start(\count($fileList));

        foreach ($fileList as $file) {
            $this->progressBar->advance();
            $this->progressBar->setMessage($file);

            if ($this->input->getOption('check-by-name')) {
                $fileInfo = new \SplFileInfo($file);
                $name = $fileInfo->getFilename();

                $names[$file] = $name;
            } else {
                $hash = hash_file('sha3-256', $file);

                $hashes[$hash] = $file;
            }
        }

        $this->progressBar->finish();

        return [$names, $hashes];
    }

    /**
     * @param array $list
     */
//    protected function duplicationCheckStrategy(array $list) : void
//    {
//        [$names, $hashes] = $list;
//        $name = 'Interactive';
//
//        if (!$this->input->getOption('interactive')) {
//            $name = 'No' . $name;
//        }
//
//        if ($this->input->getOption('check-by-name')) {
//            /** @var \ToolsCli\Tools\Fs\Duplicated\Name $checkByName */
//            $checkByName = $this->register->factory(Name::class);
//            $hashes = $checkByName->checkByName($names, $hashes, $this->input->getOption('check-by-name'));
//        }
//
//        /** @var Strategy $strategy */
//        $strategy = $this->register->factory('ToolsCli\Tools\Fs\Duplicated\\' . $name, [$this]);
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
