<?php

/**
 * @todo implement https://github.com/hollodotme/fast-cgi-client
 * @todo check that source & destination dir exists
 */

namespace ToolsCli\Tools\Fs;

use Symfony\Component\Console\{
    Input\InputInterface,
    Input\InputArgument,
    Output\OutputInterface,
    Helper\ProgressBar,
};
use ToolsCli\Console\{
    Command,
    Alias,
};
use ToolsCli\Console\Display\Style;
use BlueRegister\{
    Register, RegisterException
};
use BlueFilesystem\Fs;

class NameToDateTool extends Command
{
    protected $commandName = 'fs:name-to-date';

    protected $errors = [];
    protected $warnings = [];

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Style
     */
    protected $style;

    /**
     * @var string
     */
    protected $messageFormat = ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%';

    /**
     * @var ProgressBar
     */
    protected $progressBar;

    /**
     * @var Register
     */
    protected $register;

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

    protected function configure() : void
    {
        $this->setName($this->commandName)
            ->setDescription($this->getAlias() . 'Convert files into files named by create time.')
            ->setHelp('');

        $this->addArgument(
            'source',
            InputArgument::REQUIRED,
            'source files to convert'
        );

        $this->addArgument(
            'destination',
            InputArgument::REQUIRED,
            'destination of renamed files'
        );

        $this->addOption(
            'date-format',
            'df',
            InputArgument::OPTIONAL,
            'date() accepted format',
            'Y-m-d_H:i:s'
        );

        $this->addOption(
            'format-check',
            'c',
            null,
            'check some defined datetime in filename as valid creation time'
        );

        $this->addOption(
            'exists',
            'e',
            null,
            'check that all converted files exists'
        );

        $this->addOption(
            'delete',
            'd',
            null,
            'remove converted files'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output) : void
    {
        $this->output = $output;
        $this->style = new Style($input, $output, $this);

        try {
            $this->progressBar = $this->register->factory(ProgressBar::class, [$output]);
        } catch (RegisterException $exception) {
            throw new \Exception('RegisterException: ' . $exception->getMessage());
        }

        $this->progressBar->setFormat($this->messageFormat);

        $mainDir = rtrim($input->getArgument('source'), '/');
        $destination = rtrim($input->getArgument('destination'), '/');
        $list = glob($mainDir . '/*');
        $filenameCollision = [];
        $contentCollision = [];
        $newFiles = [];
        $newFilesFull = [];
        $all = \count($list);
        $skipped = 0;
        $notExists = 0;
        $count = 0;

        $this->style->infoMessage('Name to date conversion started. All elements: <fg=red>' . $all . '</>');
        $this->style->newLine();

        if (!$this->output->isVerbose()) {
            $this->progressBar->start(\count($list));
        }

        foreach ($list as $item) {
            $file = new \SplFileInfo($item);

            if (!$this->output->isVerbose()) {
                $this->progressBar->advance();
                $this->progressBar->setMessage($file->getBasename());
            }


            if (!$file->isFile()) {
                continue;
            }

            $hash = md5(file_get_contents($item));

            if (\in_array($hash, $contentCollision, true)) {
                $skipped++;

                if ($output->isVerbose()) {
                    $this->showMessage(
                        'content collision detected: <fg=red>' . $mainDir . '/' . $file->getBasename() . '</>',
                        'warning'
                    );
                }

                continue;
            }

            $contentCollision[] = $hash;

            if ($input->getOption('format-check')) {
                $fileCreationDate = $this->checkFormat($file);
            } else {
                $fileCreationDate = $file->getMTime();
            }

            ++$count;

            $this->showMessage(
                $file->getBasename()
                . ' -> '
                . date($input->getOption('date-format'), $fileCreationDate)
                . ' - <fg=red>'
                . $count
                . '</> / '
                . ($all - $skipped),
                'success'
            );

            $newName = date($input->getOption('date-format'), $fileCreationDate);
            $newPath = $destination . '/' . $newName;
            $extension = $this->getExtension($file);

            if (\in_array($newPath, $newFiles, true)) {
                if (!isset($filenameCollision[$newPath])) {
                    $filenameCollision[$newPath] = 0;
                }

                $newPath .= '-' . ++$filenameCollision[$newPath];

                $this->showMessage('collision detected: <comment>' . $newPath . '</comment>', 'warning');
            }

            $newFiles[] = $newPath;
            $newFilesFull[] = $newPath . $extension;
            $oldFile = $mainDir . '/' . $file->getBasename();

            Fs::copy(
                $oldFile,
                $newPath . $extension
            ) ? $this->showMessage('copy success', 'success') : $this->showMessage('copy fail', 'error');

            if ($input->getOption('delete')) {
                unlink($oldFile)
                    ? $this->showMessage('delete success', 'success')
                    : $this->showMessage('delete fail', 'error');
            }
        }

        if (!$this->output->isVerbose()) {
            $this->progressBar->finish();
        }

        $this->style->newLine();
        $this->style->okMessage('Converted files: <info>' . $count . '</info>');
        $this->style->newLine();

        if ($input->getOption('exists')) {
            foreach ($newFilesFull as $file) {
                if (!file_exists($file)) {
                    $this->showMessage("File don't exists: $file", 'error');
                    $notExists++;
                }
            }

            if ($notExists > 0) {
                $this->style->errorMessage("Not existing files: $notExists");
            }
        }

        if (!$this->output->isVerbose()) {
            foreach ($this->warnings as $warning) {
                $this->style->warningMessage($warning);
            }
            foreach ($this->errors as $error) {
                $this->style->warningMessage($error);
            }
        }

        $this->style->newLine();
    }

    /**
     * @param string $message
     * @param string $type
     * @return $this
     */
    protected function showMessage(string $message, string $type) : self
    {
        switch (true) {
            case $type === 'warning' && $this->output->isVerbose():
                $this->style->warningMessage($message);
                break;

            case $type === 'error' && $this->output->isVerbose():
                $this->style->errorMessage($message);
                break;

            case $type === 'success' && $this->output->isVeryVerbose():
                $this->style->okMessage($message);
                break;

            case !$this->output->isVerbose() && $type === 'error':
                $this->errors[] = $message;
                break;

            case !$this->output->isVerbose() && $type === 'warning':
                $this->warnings[] = $message;
                break;

            case $type !== 'warning' && $type !== 'error' && $type !== 'success':
                $this->style->writeln($message);
                break;
        }

        return $this;
    }

    /**
     * @param \SplFileInfo $file
     * @return string
     */
    protected function getExtension(\SplFileInfo $file) : string
    {
        return '.' . strtolower($file->getExtension());
    }

    /**
     * @param \SplFileInfo $file
     * @return int
     */
    protected function checkFormat(\SplFileInfo $file) : int
    {
        $formats = $this->fileFormats();

        foreach ($formats as $format) {
            $matches = [];

            if (preg_match("#{$format[0]}#", $file->getBasename(), $matches)) {
                return $format[1]($matches[0]);
            }
        }

        return $file->getMTime();
    }

    /**
     * @return array
     */
    protected function fileFormats() : array
    {
        return [
            [
                '^[\d]{8}_[\d]{6}',
                function ($name) {
                    return strtotime(
                        str_replace('_', '', $name)
                    );
                }
            ],
            [
                '^(VID|IMG)-[\d]{8}-WA[\d]{4}',
                function ($name) {
                    $strings = explode('-', $name);

                    return strtotime(
                        str_replace('_', '', $strings[1])
                    );
                }
            ],
            [
                '^(Resized|IMG)_[\d]{8}_[\d]{4,6}',
                function ($name) {
                    $strings = explode('_', $name);
                    $part = substr($strings[2], 0, 6);

                    return strtotime(
                        str_replace('_', '', $strings[1] . $part)
                    );
                }
            ],
        ];
    }
}
