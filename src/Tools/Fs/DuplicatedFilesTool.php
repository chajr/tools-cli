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
use BlueFilesystem\StaticObjects\Structure;
use BlueFilesystem\StaticObjects\Fs;
use BlueData\Data\Formats;
use BlueRegister\{
    Register, RegisterException
};
use BlueConsole\Style;
use ToolsCli\Tools\Fs\Duplicated\Name;
use React\EventLoop\Factory;
use React\ChildProcess\Process;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

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
            'compare files using their file names. As arg give comparision parameter'
        );

        $this->addOption(
            'progress-info',
            'p',
            null,
            'Show massage on progress bar (like filename during hashing)'
        );

        $this->addOption(
            'thread',
            't',
            InputArgument::OPTIONAL,
            'Set number of threads used to calculate hash',
            0
        );
        
        /**
         * @todo thread progress
         * @todo check by size first
         * @todo read multiple directories
         */

//        $this->addOption(
//            'hash',
//            '',
//            null,
//            'display files hashes ??? only duplicated, all files??'
//        );
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
            /** @var Structure $structure */
            $structure = $this->register->factory(Structure::class, [$input->getArgument('source'), true]);
        } catch (RegisterException $exception) {
            throw new \Exception('RegisterException: ' . $exception->getMessage());
        }

        $this->blueStyle->title('Check file duplications');
        $this->blueStyle->infoMessage('Reading directory.');
        $fileList = $structure->returnPaths()['file'];
        $allFiles = \count($fileList);
        $this->blueStyle->infoMessage("All files to check: <info>$allFiles</>");

        $this->blueStyle->infoMessage('Building file hash list.');
        $hashFiles = [];
        $data = [
            'hashes' => [],
            'names' => [],
        ];

        if ($input->getOption('thread') > 0) {
            $threads = $input->getOption('thread');
            $chunkValue = \round(\count($fileList) / $threads);
            $processArrays = \array_chunk($fileList, $chunkValue);

            $loop = Factory::create();
            $dir = __DIR__;
            $counter = 0;

            foreach ($processArrays as $processArray) {
                $counter++;
                $hashes = \json_encode($processArray, JSON_THROW_ON_ERROR, 512);

                $uuid = $this->getUuid();
                $path = "$dir/../../../var/tmp/dup/$uuid.json";
                $hashFiles[] = $path;
                \file_put_contents($path, $hashes);

                $first = new Process("php $dir/Duplicated/Hash.php $path");
                $first->start($loop);
                $self = $this;

                $first->stdout->on('data', static function ($chunk) use (&$data, $path) {
                    //check $chunk is there some error message

                    try {
                        $data = \array_merge_recursive(
                            $data,
                            \json_decode(\file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)
                        );
                    } catch (RuntimeException | JsonException $exception) {
                        $this->blueStyle->errorMessage($exception->getMessage());
                    }
                });

                $first->on('exit', static function ($code) use ($counter, $self) {
                    $self->blueStyle->infoMessage("Process <options=bold>$counter</> exited with code <info>$code</>");
                });
            }

            $loop->run();
        } else {
            $this->progressBar = $this->register->factory(ProgressBar::class, [$output]);

            $this->progressBar->setFormat(
                $this->messageFormat . ($this->input->getOption('progress-info') ? '%message%' : '')
            );
        }

        foreach ($hashFiles as $hasFile) {
            Fs::delete($hasFile);
        }

        $this->blueStyle->infoMessage('Compare files.');
        $this->duplicationCheckStrategy($data);

//        if ($input->getOption('interactive')) {
//            $this->blueStyle->infoMessage('Deleted files: ' . $this->deleteCounter);
//            $this->blueStyle->infoMessage('Deleted files size: ' . Formats::dataSize($this->deleteSizeCounter));
//            $this->blueStyle->newLine();
//        }

        $this->blueStyle->infoMessage('Duplicated files: ' . $this->duplicatedFiles);
        $this->blueStyle->infoMessage('Duplicated files size: ' . Formats::dataSize($this->duplicatedFilesSize));
        $this->blueStyle->newLine();
    }

    /**
     * @return string
     */
    protected function getUuid(): string
    {
        try {
            $uuid5 = Uuid::uuid4();

            return $uuid5->toString();
        } catch (\Exception | UnsatisfiedDependencyException $exception) {
            return \hash_file('sha3-256', \microtime(true));
        }
    }

    /**
     * @param array $list
     * @throws \Exception
     */
    protected function duplicationCheckStrategy(array $list) : void
    {
        $hashes = $list['hashes'];
        $names = $list['names'];
        $name = 'Interactive';

        if (!$this->input->getOption('interactive')) {
            $name = 'No' . $name;
        }

        try {
            if ($this->input->getOption('check-by-name')) {
                /** @var \ToolsCli\Tools\Fs\Duplicated\Name $checkByName */
                $checkByName = $this->register->factory(Name::class);
                $hashes = $checkByName->checkByName($names, $hashes, $this->input->getOption('check-by-name'));
            }

            /** @var Strategy $strategy */
            $strategy = $this->register->factory('ToolsCli\Tools\Fs\Duplicated\\' . $name, [$this]);
        } catch (RegisterException $exception) {
            throw new \Exception('RegisterException: ' . $exception->getMessage());
        }

        foreach ($hashes as $hash) {
            if (\count($hash) > 1) {
                  $strategy->checkByHash($hash);
            }
        }

        [$this->duplicatedFiles, $this->duplicatedFilesSize, $this->deleteCounter, $this->deleteSizeCounter]
            = $strategy->returnCounters();
    }
}
