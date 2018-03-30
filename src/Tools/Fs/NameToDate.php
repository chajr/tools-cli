<?php

namespace ToolsCli\Tools\Fs;

use Symfony\Component\Console\{
    Command\Command,
    Input\InputInterface,
    Input\InputArgument,
    Output\OutputInterface,
};
use ToolsCli\Console\Display\Style;

class NameToDate extends Command
{
    protected function configure() : void
    {
        $this->setName('fs:name-to-date')
            ->setDescription('Convert files into files named by create time.')
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

        foreach ($list as $item) {
            $file = new \SplFileInfo($item);

            if (!$file->isFile()) {
                continue;
            }

            $hash = md5(file_get_contents($item));

            if (\in_array($hash, $contentCollision, true)) {
                $style->warningMessage('content collision detected: ');
                $style->warningMessage($mainDir . '/' . $file->getBasename());
                $style->newLine();
                continue;
            }

            $contentCollision[] = $hash;

            $style->infoMessage(
                $file->getBasename()
                . ' -> '
                . date($input->getOption('date-format'), $file->getMTime())
                . ' - '
                . ++$count
            );

            $newName = date($input->getOption('date-format'), $file->getMTime());
            $newPath = $destination . '/' . $newName;

            if (file_exists($newPath . '.' . $file->getExtension())) {
                if (!isset($filenameCollision[$newPath])) {
                    $filenameCollision[$newPath] = 0;
                }

                $newPath .= '-' . ++$filenameCollision[$newPath];

                $style->newLine();
                $style->warningMessage('collision detected: ');
                $style->warningMessage($newPath);
            }

            $style->newLine();
            copy(
                $mainDir . '/' . $file->getBasename(),
                $newPath . '.' . $file->getExtension()
            ) ? $style->okMessage('copy success') : $style->errorMessage('copy fail');

            $style->newLine();
            
            
            //summary
        }
    }
}
