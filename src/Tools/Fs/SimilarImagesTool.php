<?php

/**
 * @author Michał Adamiak <chajr@bluetree.pl>
 */

declare(strict_types=1);

namespace ToolsCli\Tools\Fs;

use BlueConsole\Style;
use Symfony\Component\Console\{Input\InputInterface,
    Input\InputArgument,
    Input\InputOption,
    Output\OutputInterface,
    Helper\FormatterHelper,
    Helper\ProgressBar};
use ToolsCli\Console\{
    Command,
    Alias,
};
use BlueFilesystem\StaticObjects\{
    Structure,
    Fs,
};
use BlueRegister\{
    Register, RegisterException
};
use React\{
    EventLoop\Factory,
    ChildProcess\Process,
    EventLoop\LoopInterface,
};
use Grafika\Grafika;

class SimilarImagesTool extends Command
{
    public const TMP_DUMP_DIR = __DIR__ . '/../../../var/tmp/dupimg/';

    protected Register $register;
    protected InputInterface $input;
    protected OutputInterface $output;
    protected Style $blueStyle;
    protected FormatterHelper $formatter;
    protected string $messageFormat = '[ <fg=blue>INFO</> ]     %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% - %message%';
    protected ProgressBar $progressBar;

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

    /**
     * @return InputInterface
     */
    public function getInput(): InputInterface
    {
        return $this->input;
    }

    protected function configure(): void
    {
        $this->setName('fs:similar-img')
            ->setDescription('Search files duplication and make some action on it.')
            ->setHelp('');

        $this->addArgument(
            'source',
            InputArgument::IS_ARRAY,
            'source files to check'
        );

        $this->addOption(
            'thread',
            't',
            InputArgument::OPTIONAL,
            'Set number of threads used to calculate hash',
            0
        );

        $this->addOption(
            'progress-info',
            'p',
            null,
            'Show massage on progress bar (like filename during hashing)'
        );

        $this->addOption(
            'output',
            'o',
            null,
            'Save result into html file, instead cli output.'
        );

        $this->addOption(
            'level',
            'l',
            InputOption::VALUE_OPTIONAL,
            'Set comparator level, from 0-100 (0 means identical images).',
            10
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;

        $oldDup = \glob(self::TMP_DUMP_DIR . '*.json');
        foreach ($oldDup as $dupJson) {
            Fs::delete($dupJson);
        }

        try {
            $this->formatter = $this->register->factory(FormatterHelper::class);
            $this->blueStyle = $this->register->factory(Style::class, [$this->input, $output, $this->formatter]);
        } catch (RegisterException $exception) {
            throw new \DomainException('RegisterException: ' . $exception->getMessage());
        }

        $this->blueStyle->title('Check image duplications.');
        $this->blueStyle->infoMessage('Reading directory.');

        $fileList = $this->readDirectories($this->input->getArgument('source'));
        $allFiles = \count($fileList);
        $data = [];

        $this->blueStyle->infoMessage("All files to check: <info>$allFiles</>");
        $this->blueStyle->infoMessage('Building file list.');
        
        //jedna lista wszystkich plików
        //dodatkowo podzielona na osobne listy (zależy od wątków)
        //jedna lista porównywana w procesach z tymi podzielonymi listami

//        if ($this->input->getOption('thread') > 0) {
//            $data = $this->useThreads($fileList, $data, (int)$this->input->getOption('chunk'));
//        } else {
            $data = $this->useSingleProcess($fileList, $data);
//        }

//        dump($data);

        $this->blueStyle->infoMessage('Compare files.');
        $data = $this->useSingleProcessCompare($data);

        if (empty($data)) {
            $this->blueStyle->okMessage('No similar images founded on given level.');
            return;
        }

        $this->blueStyle->newLine();
        $this->blueStyle->infoMessage('Prepare images list.');
    }

    protected function useSingleProcessCompare($data)
    {
        $editor = Grafika::createEditor(); // Create editor
        $iterations = [];
        $checked = [];

        try {
            $progressBar = $this->register->factory(ProgressBar::class, [$this->output]);
        } catch (RegisterException $exception) {
            throw new \DomainException('RegisterException: ' . $exception->getMessage());
        }

        $progressBar->setFormat($this->messageFormat);
        $progressBar->start(\count($data));

        foreach ($data as $index => $fileMain) {
            $founded = [];

            if ($this->input->getOption('progress-info')) {
                $progressBar->setMessage($fileMain['path'] . '/' . $fileMain['name'] . "\n");
            }

            $progressBar->advance();

            try {
                $progressBar2 = $this->register->factory(ProgressBar::class, [$this->output]);
            } catch (RegisterException $exception) {
                throw new \DomainException('RegisterException: ' . $exception->getMessage());
            }

            $progressBar2->setFormat($this->messageFormat);
            $progressBar2->start(\count($data));

            foreach ($data as $fileSecond) {
                $mainPath = $fileMain['path'] . '/' . $fileMain['name'];
                $secondPath = $fileSecond['path'] . '/' . $fileSecond['name'];

                if ($this->input->getOption('progress-info')) {
                    $progressBar2->setMessage($secondPath);
                }

                $progressBar2->advance();

                $mainPathHash = \hash('sha3-256', $mainPath . $secondPath);
                $secondPathHash = \hash('sha3-256', $secondPath . $mainPath);

                if (
                    \in_array($secondPathHash, $checked)
                    || \in_array($mainPathHash, $checked)
                    || $mainPath === $secondPath
                ) {
                    continue;
                }

                $checked[] = $mainPathHash;
                $checked[] = $secondPathHash;

                $hammingDistance = $editor->compare($mainPath, $secondPath);

                if ($hammingDistance > $this->input->getOption('level')) {
                    continue;
                }

                $founded[] = [
                    'path' => $fileSecond,
                    'level' => $hammingDistance,
                ];
            }

            $progressBar2->finish();

            echo "\r";
            echo "\033[1F";

            if (empty($founded)) {
                continue;
            }

            $iterations[$index] = [
                'main' => $fileMain,
                'founded' => $founded
            ];
        }

        $progressBar->finish();

        return $iterations;
    }

    /**
     * @param array $fileList
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function useSingleProcess(array $fileList, array $data): array
    {
        try {
            $progressBar = $this->register->factory(ProgressBar::class, [$this->output]);
        } catch (RegisterException $exception) {
            throw new \DomainException('RegisterException: ' . $exception->getMessage());
        }

        $progressBar->setFormat($this->messageFormat);
        $progressBar->start(\count($fileList));

        foreach ($fileList as $file) {
            if ($this->input->getOption('progress-info')) {
                $progressBar->setMessage($file);
            }

            $progressBar->advance();

            if (\filesize($file) === 0) {
                continue;
            }

            $fileInfo = new \SplFileInfo($file);

            $data[] = [
                'name' => $fileInfo->getFilename(),
                'path' => $fileInfo->getPath(),
            ];
        }

        $progressBar->finish();
        echo "\n";

        return $data;
    }

    /**
     * @param array $fileList
     * @param array $data
     * @param int $chunk
     * @return array
     */
    protected function useThreads(array $fileList, array $data, int $chunk): array
    {
//        $hashFiles = [];
//        $threads = $this->input->getOption('thread');
//        $chunkValue = \ceil(\count($fileList) / $threads);
//
//        if ($chunkValue > 0) {
//            $fileList = \array_chunk($fileList, $chunkValue);
//        }
//
//        $loop = Factory::create();
//
//        $this->createProcesses($fileList, $loop, $data, $hashFiles, $chunk);
//        $loop->run();
//
//        return $data;
    }

    /**
     * @param array $processArrays
     * @param LoopInterface $loop
     * @param array $data
     * @param array $hashFiles
     * @param int $chunk
     * @return void
     */
    protected function createProcesses(
        array $processArrays,
        LoopInterface $loop,
        array &$data,
        array &$hashFiles,
        int $chunk
    ): void {
//        $progressList = [];
//        $counter = $this->input->getOption('thread');
//
//        foreach ($processArrays as $thread => $processArray) {
//            $hashes = \json_encode($processArray, JSON_THROW_ON_ERROR, 512);
//
//            $uuid = $this->getUuid();
//            $path = self::TMP_DUMP_DIR . "$uuid.json";
//            $hashFiles[] = $path;
//            /** @noinspection ReturnFalseInspection */
//            \file_put_contents($path, $hashes);
//
//            $dir = __DIR__;
//            $first = new Process("php $dir/Duplicated/Hash.php $path $chunk");
//            $first->start($loop);
//            $self = $this;
//
//            $first->stdout->on('data', static function ($chunk) use ($thread, &$progressList, $self) {
//                $response = \json_decode($chunk, true);
//                if ($response && isset($response['status']['all'], $response['status']['left'])) {
//                    $status = $response['status'];
//                    $progressList[$thread] = "Thread $thread - {$status['all']}/{$status['left']}";
//
//                    $self->renderThreadInfo($progressList);
//
//                    for ($i = 0; $i < $self->input->getOption('thread') - 1; $i++) {
//                        echo Interactive::MOD_LINE_CHAR;
//                    }
//                }
//            });
//
//            $first->on('exit', static function ($code) use (&$data, $path, &$counter, $self, &$progressList, $thread) {
//                $counter--;
//
//                try {
//                    $dataPipe = pipe($path)
//                        ->fileGetContents
//                        ->trim
//                        ->jsonDecode(_, true, 512, JSON_THROW_ON_ERROR);
//                    $data = \array_merge_recursive($data, $dataPipe());
//                } catch (\Throwable $exception) {
//                    $progressList[$thread] =
//                        "Error {$exception->getMessage()} - {$exception->getFile()}:{$exception->getLine()}";
//                }
//
//                $progressList[$thread] = "Process <options=bold>$thread</> exited with code <info>$code</>";
//
//                if ($counter === 0) {
//                    $self->renderThreadInfo($progressList);
//
//                    $self->blueStyle->newLine();
//                }
//            });
//        }
    }

    /**
     * @param array $paths
     * @return array
     * @throws \Exception
     */
    protected function readDirectories(array $paths): array
    {
        $fileList = [];

        foreach ($paths as $path) {
            try {
                /** @var Structure $structure */
                $structure = $this->register->factory(Structure::class, [$path, true]);
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $fileList = \array_merge($fileList, $structure->returnPaths()['file']);
            } catch (RegisterException $exception) {
                $this->blueStyle->errorMessage('RegisterException: ' . $exception->getMessage());
            }
        }

        return $fileList;
    }
}
