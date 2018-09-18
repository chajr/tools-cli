<?php

/**
 * @todo add progress barr https://symfony.com/doc/current/components/console/helpers/progressbar.html
 * @todo only in verbose-verbose show all info, on verbose, show only warning+error, in normal show warning+error at finish
 * @todo implement https://github.com/hollodotme/fast-cgi-client
 * @todo show info about all errors (error flag)
 */

namespace ToolsCli\Tools\Fs;

use Symfony\Component\Console\{
    Input\InputInterface,
    Input\InputArgument,
    Output\OutputInterface,
};
use ToolsCli\Console\Display\Style;
use ToolsCli\Console\Command;

class NameToDateTool extends Command
{
    protected $commandName = 'fs:name-to-date';

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
     */
    protected function execute(InputInterface $input, OutputInterface $output) : void
    {
        $style = new Style($input, $output, $this);

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

        $style->infoMessage('Name to date conversion started. All elements: <fg=red>' . $all . '</>');
        $style->newLine();

        foreach ($list as $item) {
            $file = new \SplFileInfo($item);

            if (!$file->isFile()) {
                continue;
            }

            $hash = md5(file_get_contents($item));

            if (\in_array($hash, $contentCollision, true)) {
                $skipped++;
                $style->warningMessage(
                    'content collision detected: <fg=red>' . $mainDir . '/' . $file->getBasename() . '</>'
                );
                continue;
            }

            $contentCollision[] = $hash;

            if ($input->getOption('format-check')) {
                $fileCreationDate = $this->checkFormat($file);
            } else {
                $fileCreationDate = $file->getMTime();
            }

            $style->infoMessage(
                $file->getBasename()
                . ' -> '
                . date($input->getOption('date-format'), $fileCreationDate)
                . ' - <fg=red>'
                . ++$count
                . '</> / '
                . ($all - $skipped)
            );

            $newName = date($input->getOption('date-format'), $fileCreationDate);
            $newPath = $destination . '/' . $newName;
            $extension = $this->getExtension($file);

            if (\in_array($newPath, $newFiles, true)) {
                if (!isset($filenameCollision[$newPath])) {
                    $filenameCollision[$newPath] = 0;
                }

                $newPath .= '-' . ++$filenameCollision[$newPath];

                $style->warningMessage('collision detected: <comment>' . $newPath . '</comment>');
            }

            $newFiles[] = $newPath;
            $newFilesFull[] = $newPath . $extension;
            $oldFile = $mainDir . '/' . $file->getBasename();

            /** @todo use Symfony:fs or bluetree-fs  */
            copy(
                $oldFile,
                $newPath . $extension
            ) ? $style->okMessage('copy success') : $style->errorMessage('copy fail');

            if ($input->getOption('delete')) {
                unlink($oldFile) ? $style->okMessage('delete success') : $style->errorMessage('delete fail');
            }
        }

        $style->newLine();
        $style->okMessage('Converted files: <info>' . $count . '</info>');
        $style->newLine();

        if ($input->getOption('exists')) {
            foreach ($newFilesFull as $file) {
                if (!file_exists($file)) {
                    $style->errorMessage("File don't exists: $file");
                    $notExists++;
                }
            }

            if ($notExists > 0) {
                $style->errorMessage("Not existing files: $notExists");
            }
        }
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
