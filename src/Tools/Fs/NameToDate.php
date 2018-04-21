<?php

namespace ToolsCli\Tools\Fs;

use Symfony\Component\Console\{
    Input\InputInterface,
    Input\InputArgument,
    Output\OutputInterface,
};
use ToolsCli\Console\Display\Style;
use ToolsCli\Console\Command;

class NameToDate extends Command
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
            'd',
            InputArgument::OPTIONAL,
            'date() accepted format',
            'Y-m-d_H:i:s'
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
        $count = 0;
        $filenameCollision = [];
        $contentCollision = [];

        $style->infoMessage('Name to date conversion started. All elements: <fg=red>' . \count($list) . '</>');
        $style->newLine();

        foreach ($list as $item) {
            $file = new \SplFileInfo($item);

            if (!$file->isFile()) {
                continue;
            }

            $hash = md5(file_get_contents($item));

            if (\in_array($hash, $contentCollision, true)) {
                $style->warningMessage(
                    'content collision detected: <comment>' . $mainDir . '/' . $file->getBasename() . '</comment>'
                );
                continue;
            }

            $contentCollision[] = $hash;

            $style->infoMessage(
                $file->getBasename()
                . ' -> '
                . date($input->getOption('date-format'), $file->getMTime())
                . ' - <fg=red>'
                . ++$count
                . '</>'
            );

            $newName = date($input->getOption('date-format'), $file->getMTime());
            $newPath = $destination . '/' . $newName;

            if (file_exists($newPath . '.' . $file->getExtension())) {
                if (!isset($filenameCollision[$newPath])) {
                    $filenameCollision[$newPath] = 0;
                }

                $newPath .= '-' . ++$filenameCollision[$newPath];

                $style->warningMessage('collision detected: <fg=red>' . $newPath . '</>');
            }

            /** @todo use Symfony:fs  */
            copy(
                $mainDir . '/' . $file->getBasename(),
                $newPath . '.' . $file->getExtension()
            ) ? $style->okMessage('copy success') : $style->errorMessage('copy fail');

        }

        /** @todo time and memory usage */
        /** @todo convert extension to lower case */
        /** @todo add progress barr https://symfony.com/doc/current/components/console/helpers/progressbar.html */
        /** @todo implement https://github.com/hollodotme/fast-cgi-client */

        $style->newLine();
        $style->okMessage('Converted files: <info>' . $count . '</info>');
        $style->newLine();
    }
}
