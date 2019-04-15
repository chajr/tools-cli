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
use BlueFilesystem\StaticObjects\{
    Fs,
    Structure,
};
use BlueRegister\{
    Register, RegisterException
};
use BlueConsole\Style;

class CopyAndReplaceExistsTool extends Command
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
            ->setDescription('Copy files with checking that files on the same name exists.')
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
            'i',
            null,
            'Additional progress bar information'
        );

        $this->addOption(
            'progress-bar',
            'p',
            null,
            'Show progress bar instead of messages'
        );

        $this->addOption(
            'skipped',
            's',
            null,
            'Show list of skipped files'
        );

        $this->addOption(
            'delete',
            'd',
            null,
            'Remove source dir after copy'
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

        $skippedFiles = [];
        $progressInfo = $this->input->getOption('progress-info');
        $progressBar = $this->input->getOption('progress-bar');
        $skipped = $this->input->getOption('skipped');
        $sourceDir = $input->getArgument('source');
        $structure = $this->register->factory(Structure::class, [$sourceDir]);

        try {
            $this->formatter = $this->register->factory(FormatterHelper::class);
            $this->blueStyle = $this->register->factory(Style::class, [$input, $output, $this->formatter]);
            $this->progressBar = $this->register->factory(ProgressBar::class, [$output]);
        } catch (RegisterException $exception) {
            throw new \RuntimeException('RegisterException: ' . $exception->getMessage());
        }

        if ($progressBar) {
            $this->progressBar->setFormat(
                $this->messageFormat . ($this->input->getOption('progress-info') ? '%message%' : '')
            );
        }

        $this->blueStyle->writeln('Reading directory.');
        $list = $structure->getReadDirectory($sourceDir);
        $allFiles = \count($list);
        $this->blueStyle->writeln("All files to copy: $allFiles");
        $this->blueStyle->newLine();


        if ($progressBar) {
            $this->progressBar->start($allFiles);
        }
        /** @var \SplFileInfo $file */
        foreach ($list as $file) {
            try {
                if ($progressBar) {
                    $this->progressBar->advance();
                }

                if ($progressBar && $progressInfo) {
                    $this->progressBar->setMessage($file->getFilename());
                }

                $destinationDir = $input->getArgument('destination');
                $pathToCheck    = $destinationDir . '/' . $file->getFilename();

                if (Structure::exist($pathToCheck)) {
                    $hashOne = hash_file('sha3-256', $file->getRealPath());
                    $hashTwo = hash_file('sha3-256', $pathToCheck);

                    if ($hashOne === $hashTwo) {
                        if (!$progressBar) {
                            $this->blueStyle->warningMessage(
                                'Skipping: ' . $file->getRealPath() . ' to: ' . $destinationDir
                            );
                        }
                        if ($skipped) {
                            $skippedFiles[] = $file->getRealPath();
                        }
                        $this->deleteCounter++;
                        continue;
                    }

                    $newFileName = $destinationDir . '/' . $hashOne;
                    $extension   = $file->getExtension();
                    if ($extension) {
                        $newFileName .= '.' . $file->getExtension();
                    }

                    if (Structure::exist($newFileName)) {
                        if (!$progressBar) {
                            $this->blueStyle->errorMessage('Hash file exists: ' . $newFileName);
                        }
                        if ($skipped) {
                            $skippedFiles[] = 'Hash file exists: ' . $newFileName;
                        }
                        continue;
                    }

                    if (!$progressBar) {
                        $this->blueStyle->infoMessage('Copy: ' . $newFileName . ' to: ' . $destinationDir);
                    }
                    Fs::copy($file->getRealPath(), $newFileName);
                    $this->copiedCounter++;
                } else {
                    if (!$progressBar) {
                        $this->blueStyle->infoMessage('Copy: ' . $file->getRealPath() . ' to: ' . $destinationDir);
                    }
                    Fs::copy($file->getRealPath(), $destinationDir . '/' . $file->getFilename());
                    $this->copiedCounter++;
                }
            } catch (\Exception $exception) {
                $this->blueStyle->error($exception->getMessage() . '; ' . $file->getRealPath());
            }
        }

        if ($this->input->getOption('delete')) {
            Fs::delete($sourceDir);
        }

        if ($progressBar) {
            $this->progressBar->finish();
            $this->blueStyle->newLine();
        }

        $this->blueStyle->newLine();
        $this->blueStyle->writeln('Copied files: <info>' . $this->copiedCounter . '</info>');
        $this->blueStyle->writeln('Skipped files: <info>' . $this->deleteCounter . '</info>');
        $this->blueStyle->newLine();

        if ($skipped) {
            $this->blueStyle->title('Skipped files list:');

            foreach ($skippedFiles as $info) {
                $this->blueStyle->writeln($info);
            }

            $this->blueStyle->newLine();
        }
    }
}
