<?php

namespace ToolsCli\Tools\Fs\Duplicated;

use ToolsCli\Tools\Fs\DuplicatedFilesTool;
use BlueData\Data\Formats;

class NoInteractive implements Strategy
{
    /**
     * @var \BlueConsole\Style
     */
    protected $blueStyle;

    protected $duplicatedFiles = 0;
    protected $duplicatedFilesSize = 0;

    /**
     * @param DuplicatedFilesTool $dft
     */
    public function __construct(DuplicatedFilesTool $dft)
    {
        $this->blueStyle = $dft->getBlueStyle();
    }

    /**
     * @param array $hashes
     * @return $this
     */
    public function checkByHash(array $hashes) : Strategy
    {
        foreach ($hashes as $hash) {
            if (\count($hash) > 1) {
                $this->blueStyle->writeln('Duplications:');

                foreach ($hash as $file) {
                    $this->duplicatedFiles++;
                    $size = filesize($file);
                    $this->duplicatedFilesSize += $size;

                    $formattedSize = Formats::dataSize($size);
                    $this->blueStyle->writeln("$file ($formattedSize)");
                }

                $this->blueStyle->newLine();
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function returnCounters() : array
    {
        return [
            $this->duplicatedFiles,
            $this->duplicatedFilesSize,
            0,
            0,
        ];
    }
}
