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

        if ($this->input->getOption('thread') > 0) {
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
                "<fg=yellow>$size</> <options=bold>{$imageSize[0]}</>x<options=bold>{$imageSize[1]}</> <fg=green>$main</>"
            );

            foreach ($images as $founded) {
                $fileInfo = new \SplFileInfo($founded['path']);
                $size = Formats::dataSize($fileInfo->getSize());
                $imageSize = getimagesize($founded['path']);

                $this->blueStyle->writeln(
                    "<fg=yellow>$size</> <options=bold>{$imageSize[0]}</>x<options=bold>{$imageSize[1]}</> Level: <options=bold>{$founded['level']}</>; <fg=blue>{$founded['path']}</>"
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
        $threads = (int)$this->input->getOption('thread');
        $count = \count($fileList);

        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6378);
        $session = "dupimg-" . Uuid::uuid4()->toString();

        $this->blueStyle->infoMessage("Session: <fg=green>$session</>");
        $this->blueStyle->infoMessage("Generate hashes");

        foreach ($fileList as $path) {
            $redis->rPush("$session-paths-hashes", $path['path'] . '/' . $path['name']);
        }

        $loop = Factory::create();
        $this->createProcessesHashes($threads, $loop, $session, $count, $redis);
        $loop->run();

        $errors = $redis->sMembers("$session-errors");
        foreach ($errors as $error) {
            $this->blueStyle->errorMessage($error);
        }

        $redis->del("$session-errors");
        $redis->del("$session-hash-processed");

        $this->blueStyle->infoMessage("Compare hashes");

        $loop = Factory::create();
        $this->createProcessesCompare($threads, $loop, $session, $redis);
        $loop->run();

        $errors = $redis->sMembers("$session-errors");
        foreach ($errors as $error) {
            $this->blueStyle->errorMessage($error);
        }

        for ($i = 0; $i < $threads; $i++) {
            $val = $redis->hGet("$session-founded", "thread-$i");
            if ($val) {
                $newData = \array_merge(\json_decode($val, true), $newData);
            }
        }

        try {
            $redis->del("$session-compare-processed");
            $redis->del("$session-checked");
            $redis->del("$session-founded");
            $redis->del("$session-errors");
            $redis->del("$session-hashes");
        } catch (\Throwable $exception) {
            $this->blueStyle->errorMessage('Unable to clear cache: ' . $exception->getMessage());
        }

        return $newData;
    }

    /**
     * @param int $threads
     * @param LoopInterface $loop
     * @param string $session
     * @param int $allChecks
     * @param \Redis $redis
     * @return void
     */
    protected function createProcessesHashes(
        int $threads,
        LoopInterface $loop,
        string $session,
        int $allChecks,
        \Redis $redis
    ): void {
        $progressList = [];
        $counter = (int)$this->input->getOption('thread');

        for ($thread = 0; $thread < $threads; $thread++) {
            $dir = __DIR__;
            $this->input->getOption('verbose') ? $verbose = 1 : $verbose = 0;

            $first = new Process("php $dir/Similar/ProcessHashes.php $session $thread $verbose");
            $first->start($loop);
            $self = $this;

            $first->stdout->on('data', static function ($chunk) use ($thread, &$progressList, $self, $allChecks, $threads, $redis, $session) {
                $response = \json_decode($chunk, true);

                if ($response && isset($response['status']['done'])) {
                    $sum = $redis->get("$session-hash-processed");
                    $progressList[$thread] = "Thread <options=bold>$thread</> hashes - {$response['status']['done']}";
                    $progressList[$threads] = "Generated hashes - $allChecks/$sum";

                    $self->renderThreadInfo($progressList);

                    for ($i = 0; $i < $threads; $i++) {
                        echo Interactive::MOD_LINE_CHAR;
                    }
                }
            });

            $first->on('exit', static function ($code) use (&$counter, $self, &$progressList, $thread, $threads, $redis, $session, $allChecks) {
                $counter--;

                $progressList[$thread] = "Process <options=bold>$thread</> exited with code <info>$code</>";
                $sum = $redis->get("$session-hash-processed");
                $progressList[$threads] = "Generated hashes - $allChecks/$sum";

                if ($counter === 0) {
                    $self->renderThreadInfo($progressList);

                    $self->blueStyle->newLine();
                }
            });
        }
    }

    /**
     * @param int $threads
     * @param LoopInterface $loop
     * @param string $session
     * @param \Redis $redis
     * @return void
     */
    protected function createProcessesCompare(
        int $threads,
        LoopInterface $loop,
        string $session,
        \Redis $redis
    ): void {
        $progressList = [];
        $counter = (int)$this->input->getOption('thread');
        $count = $redis->lLen("$session-paths-compare");
        $allChecks = $count * $count;

        for ($thread = 0; $thread < $threads; $thread++) {
            $level = $this->input->getOption('level');
            $dir = __DIR__;
            $this->input->getOption('verbose') ? $verbose = 1 : $verbose = 0;

            $first = new Process("php $dir/Similar/ProcessImages.php $level $session $thread $verbose");
            $first->start($loop);
            $self = $this;

            $first->stdout->on('data', static function ($chunk) use ($thread, &$progressList, $self, $allChecks, $threads, $redis, $session) {
                $response = \json_decode($chunk, true);

                if ($response && isset($response['status']['done'])) {
                    $sum = $redis->get("$session-compare-processed");
                    $progressList[$thread] = "Thread <options=bold>$thread</> compares - {$response['status']['done']}";
                    $progressList[$threads] = "All compares - $allChecks/$sum";

                    $self->renderThreadInfo($progressList);

                    for ($i = 0; $i < $threads; $i++) {
                        echo Interactive::MOD_LINE_CHAR;
                    }
                }
            });

            $first->on('exit', static function ($code) use (&$counter, $self, &$progressList, $thread, $threads, $redis, $session, $allChecks) {
                $counter--;

                $progressList[$thread] = "Process <options=bold>$thread</> exited with code <info>$code</>";
                $sum = $redis->get("$session-compare-processed");
                $progressList[$threads] = "All compares - $allChecks/$sum";

                if ($counter === 0) {
                    $self->renderThreadInfo($progressList);

                    $self->blueStyle->newLine();
                }
            });
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
