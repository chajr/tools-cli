<?php

/**
 * @author Michał Adamiak <chajr@bluetree.pl>
 */

declare(strict_types=1);

namespace ToolsCli\Tools\Fs;

use BlueConsole\Style;
use Symfony\Component\Console\{
    Input\InputInterface,
    Input\InputArgument,
    Input\InputOption,
    Output\OutputInterface,
    Helper\FormatterHelper,
    Helper\ProgressBar
};
use ToolsCli\Console\{
    Command,
    Alias,
};
use Ramsey\Uuid\{
    Exception\UnsatisfiedDependencyException,
    Uuid,
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
use ToolsCli\Tools\Fs\Duplicated\Interactive;
use BlueData\Data\Formats;

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
    protected ?\Redis $redis;
    protected string $session = '';
    protected int $threads = 0;

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

        $this->addOption(
            'save',
            's',
            null,
            'Save result in given file name.'
        );

        $this->addOption(
            'compare-hash',
            'H',
            null,
            'Search identical images by hash before checking all images.'
        );

        $this->addOption(
            'identical',
            'I',
            null,
            'Search only identical images (compression can cause some differences).'
        );

        $this->addOption(
            'split',
            'S',
            InputOption::VALUE_OPTIONAL,
            'Split output files for given number of found values.'
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

        $data = $this->getFilesData($fileList, $data);

        $this->blueStyle->infoMessage('Compare files.');
        $this->threads = (int)$this->input->getOption('thread');

        if ($this->threads > 0) {
            $data = $this->useThreads($data);
        } else {
            $data = $this->useSingleProcessCompare($data);
        }

        if (empty($data)) {
            $this->blueStyle->okMessage('No similar images founded on given level.');
            return;
        }

        $this->blueStyle->newLine();
        $this->blueStyle->infoMessage('Prepare images list.');

        if (!$this->input->getOption('output')) {
            $this->cli($data);
        } else {
            $this->html($data);
        }
    }

    /**
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function useSingleProcessCompare(array $data): array
    {
        $editor = Grafika::createEditor();
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
    protected function getFilesData(array $fileList, array $data): array
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
     * @param array $data
     * @return void
     */
    protected function cli(array $data): void
    {
        $counter = 1;

        foreach ($data as $main => $images) {
            $this->blueStyle->title('Group: ' . $counter++);
            $fileInfo = new \SplFileInfo($main);
            $size = Formats::dataSize($fileInfo->getSize());
            $imageSize = getimagesize($main);

            $this->blueStyle->writeln(
                "<fg=yellow>$size</> <options=bold>{$imageSize[0]}</>"
                . "x<options=bold>{$imageSize[1]}</> <fg=green>$main</>"
            );

            foreach ($images as $founded) {
                $fileInfo = new \SplFileInfo($founded['path']);
                $size = Formats::dataSize($fileInfo->getSize());
                $imageSize = getimagesize($founded['path']);

                $this->blueStyle->writeln(
                    "<fg=yellow>$size</> <options=bold>{$imageSize[0]}</>x<options=bold>{$imageSize[1]}</>"
                    . " Level: <options=bold>{$founded['level']}</>; <fg=blue>{$founded['path']}</>"
                );
            }
        }
    }

    /**
     * @param array $data
     * @return void
     * @throws \Exception
     */
    protected function html(array $data): void
    {
        $split = (int)$this->input->getOption('split');
        $renderList = '<html><body>';
        $count = 0;
        $countFile = 0;

        foreach ($data as $main => $images) {
            $count++;
            $fileInfo = new \SplFileInfo($main);
            $size = Formats::dataSize($fileInfo->getSize());
            $encoded = str_replace("%", "%25", $main);
            $imageSize = getimagesize($main);

            $renderList .= '<div style="margin: 10px; padding: 10px; border: 1px solid black">';
            $renderList .= "<img src='$encoded' width='400px'/>";
            $renderList .= " $size {$imageSize[0]}x{$imageSize[1]} ";
            $renderList .= " <a target='blank' href=\"$encoded\">$main</a><br/>";

            foreach ($images as $founded) {
                $full = $founded['path'];
                $fileInfo = new \SplFileInfo($full);
                $size = Formats::dataSize($fileInfo->getSize());
                $encoded = str_replace("%", "%25", $full);
                $imageSize = getimagesize($full);

                $renderList .= "<img src='$encoded' width='400px'/>";
                $renderList .= "$size {$imageSize[0]}x{$imageSize[1]} <span>Level: {$founded['level']}</span> ";
                $renderList .= " <a target='blank' href=\"$encoded\">$full</a><br/>";
            }

            $renderList .= '</div>';

            if ($split && $split === $count) {
                $count = 0;
                $renderList .= '</body></html>';

                file_put_contents("similar_images_output_$countFile.html", $renderList);
                $countFile++;

                $renderList = '<html><body>';
            } else {
                $renderList .= '</body></html>';
            }
        }

        $saved = file_put_contents('similar_images_output.html', $renderList);

        if ($saved) {
            $this->blueStyle->okMessage('File saved.');
            return;
        }

        $this->blueStyle->errorMessage('Unable to save file.');
    }

    /**
     * @param array $fileList
     * @return array
     * @throws \Exception
     */
    protected function useThreads(array $fileList): array
    {
        $newData = [];
        $this->threads = (int)$this->input->getOption('thread');
        $count = \count($fileList);
        $threadIdentical = 0;

        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6378);
        $this->session = "dupimg-" . Uuid::uuid4()->toString();

        $this->blueStyle->infoMessage("Session: <fg=green>$this->session</>");
        $this->blueStyle->infoMessage("Generate hashes");

        foreach ($fileList as $path) {
            $this->redis->rPush("$this->session-paths-hashes", $path['path'] . '/' . $path['name']);
        }

        $loop = Factory::create();
        $this->createProcessesHashes($loop, $count);
        $loop->run();

        $errors = $this->redis->sMembers("$this->session-errors");
        foreach ($errors as $error) {
            $this->blueStyle->errorMessage($error);
        }

        $this->clearCache(['hash-processed', 'errors']);

        if ($this->input->getOption('compare-hash') || $this->input->getOption('identical')) {
            $this->blueStyle->infoMessage("Find identical.");
            $identical = $this->processIdentical();
            $this->blueStyle->newLine();
            $this->blueStyle->infoMessage("Identical founded: <fg=green>$identical</>.");

            $this->clearCache(['hashes-identical']);

            $threadIdentical = 1;
        }

        $progressBar = null;
        $this->blueStyle->infoMessage('Merge founded images.');

        if (!$this->input->getOption('identical')) {
            $this->blueStyle->infoMessage("Compare hashes.");

            $loop = Factory::create();
            $this->createProcessesCompare($loop);
            $loop->run();

            $errors = $this->redis->sMembers("$this->session-errors");
            foreach ($errors as $error) {
                $this->blueStyle->errorMessage($error);
            }

            try {
                $progressBar = $this->register->factory(ProgressBar::class, [$this->output]);
            } catch (RegisterException $exception) {
                throw new \DomainException('RegisterException: ' . $exception->getMessage());
            }

            $progressBar->setFormat($this->messageFormat);
            $progressBar->start($this->threads + $threadIdentical);
        }

        if (!$this->input->getOption('identical')) {
            for ($i = 0; $i < $this->threads; $i++) {
                if ($this->input->getOption('progress-info')) {
                    $progressBar->setMessage("thread $i");
                }
                $progressBar->advance();

                $val = $this->redis->hGet("$this->session-founded", "thread-$i");
                if ($val) {
                    $newData = \array_merge(\json_decode($val, true), $newData);
                }
            }
        }

        if ($this->input->getOption('compare-hash') || $this->input->getOption('identical')) {
            $progressBar?->advance();

            $val = $this->redis->hGet("$this->session-founded", "thread-x");
            if ($val) {
                $threadX = \json_decode($val, true);

                foreach ($threadX as $mainPath => $founded) {
                    if (\array_key_exists($mainPath, $newData)) {
                        $newData[$mainPath] = \array_merge($newData[$mainPath], $founded);
                    } else {
                        $newData[$mainPath] = $founded;
                    }
                }
            }
        }

        $progressBar?->finish();

        $this->blueStyle->newLine();
        $this->blueStyle->infoMessage('Cache clearing');

        try {
            $this->clearCache(['compare-processed', 'checked', 'founded', 'errors', 'hashes']);
            $this->blueStyle->okMessage('Cache clear done');
        } catch (\Throwable $exception) {
            $this->blueStyle->errorMessage('Unable to clear cache: ' . $exception->getMessage());
        }

        return $newData;
    }

    /**
     * @param array $types
     * @return void
     */
    protected function clearCache(array $types): void
    {
        foreach ($types as $type) {
            $this->redis->del("$this->session-$type");
        }
    }

    /**
     * @return int
     * @throws \JsonException
     */
    protected function processIdentical(): int
    {
        try {
            $progressBar = $this->register->factory(ProgressBar::class, [$this->output]);
        } catch (RegisterException $exception) {
            throw new \DomainException('RegisterException: ' . $exception->getMessage());
        }

        $identical = $this->redis->hGetAll("$this->session-hashes-identical");
        $progressBar->setFormat($this->messageFormat);
        $progressBar->start(\count($identical));
        $identicalCount = 0;

        foreach ($identical as $list) {
            $decoded = \json_decode($list, true);

            $progressBar->setMessage("");
            $progressBar->advance();

            if (\count($decoded) > 1) {
                $mainPath = \array_shift($decoded);
                $founded = [];

                foreach ($decoded as $secondPath) {
                    $identicalCount++;
                    $mainPathHash = "$mainPath;;;$secondPath";
                    $secondPathHash = "$secondPath;;;$mainPath";

                    $this->redis->sAdd("$this->session-checked", $mainPathHash);
                    $this->redis->sAdd("$this->session-checked", $secondPathHash);

                    $founded[$mainPath][] = [
                        'path' => $secondPath,
                        'level' => 0,
                    ];
                }

                $foundedMain = $this->redis->hGet("$this->session-founded", "thread-x");

                if ($foundedMain) {
                    $founded = \array_merge(\json_decode($foundedMain, true), $founded);
                }

                $this->redis->hSet(
                    "$this->session-founded",
                    "thread-x",
                    \json_encode($founded, JSON_THROW_ON_ERROR, 512)
                );
            }
        }

        $progressBar->finish();

        return $identicalCount;
    }

    /**
     * @param LoopInterface $loop
     * @param int $allChecks
     * @return void
     */
    protected function createProcessesHashes(LoopInterface $loop, int $allChecks): void
    {
        $identical = ($this->input->getOption('compare-hash') || $this->input->getOption('identical')) ? 1 : 0;
        $this->input->getOption('verbose') ? $verbose = 1 : $verbose = 0;
        $dir = __DIR__;

        $this->process(
            "php $dir/Similar/ProcessHashes.php $this->session $verbose $identical",
            $loop,
            $allChecks,
            'hash-processed',
            'hashes'
        );
    }

    /**
     * @param LoopInterface $loop
     * @return void
     */
    protected function createProcessesCompare(LoopInterface $loop): void
    {
        $count = $this->redis->lLen("$this->session-paths-compare");
        $allChecks = $count * $count;
        $level = $this->input->getOption('level');
        $this->input->getOption('verbose') ? $verbose = 1 : $verbose = 0;
        $dir = __DIR__;

        $this->process(
            "php $dir/Similar/ProcessImages.php $level $this->session $verbose",
            $loop,
            $allChecks,
            'compare-processed',
            'compares'
        );
    }

    /**
     * @param string $processCommand
     * @param LoopInterface $loop
     * @param int $allChecks
     * @param string $keyName
     * @param string $type
     * @return void
     */
    protected function process(
        string $processCommand,
        LoopInterface $loop,
        int $allChecks,
        string $keyName,
        string $type
    ): void {
        $progressList = [];
        $counter = (int)$this->input->getOption('thread');

        for ($thread = 0; $thread < $this->threads; $thread++) {
            $first = new Process("$processCommand $thread");
            $first->start($loop);
            $self = $this;

            $first->stdout->on(
                'data',
                static function ($chunk) use ($thread, &$progressList, $self, $allChecks, $keyName, $type) {
                    $response = \json_decode($chunk, true);

                    if ($response && isset($response['status']['done'])) {
                        $sum = $self->redis->get("$self->session-$keyName");
                        $progressList[$thread] = "Thread <options=bold>$thread</> $type - {$response['status']['done']}";
                        $progressList[$self->threads] = "Generated $type - $allChecks/$sum";

                        $self->renderThreadInfo($progressList);

                        for ($i = 0; $i < $self->threads; $i++) {
                            echo Interactive::MOD_LINE_CHAR;
                        }
                    }
                }
            );

            $first->on(
                'exit',
                static function ($code) use (&$counter, $self, &$progressList, $thread, $allChecks, $keyName, $type) {
                    $counter--;

                    $progressList[$thread] = "Process <options=bold>$thread</> exited with code <info>$code</>";
                    $sum = $self->redis->get("$self->session-$keyName");
                    $progressList[$self->threads] = "All $type - $allChecks/$sum";

                    if ($counter === 0) {
                        $self->renderThreadInfo($progressList);

                        $self->blueStyle->newLine();
                    }
                }
            );
        }
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
     * @param array $progressList
     * @throws \Exception
     */
    protected function renderThreadInfo(array $progressList): void
    {
        for ($i = 0; $i < $this->input->getOption('thread') + 1; $i++) {
            if ($i > 0) {
                echo "\n";
            }

            $message = $progressList[$i] ?? '';
            echo "\r";

            /** @noinspection ReturnFalseInspection */
            if (\strpos($message, 'Error') === 0) {
                $this->blueStyle->errorMessage($message);
            } else {
                $this->blueStyle->infoMessage($message);
            }

            echo Interactive::MOD_LINE_CHAR;
        }
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
