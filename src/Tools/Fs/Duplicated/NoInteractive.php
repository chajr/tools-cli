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

    protected $duplicatedFilesSize = 0;

    /**
     * @param DuplicatedFilesTool $dft
     */
    public function __construct(DuplicatedFilesTool $dft)
    {
        $this->blueStyle = $dft->getBlueStyle();
    }

    /**
     * @param array $hash
     * @return $this
     */
    public function checkByHash(array $hash) : Strategy
    {
        $this->blueStyle->writeln('Duplications:');

        foreach ($hash as $file) {
            $size = filesize($file);
            $this->duplicatedFilesSize += $size;

            $formattedSize = Formats::dataSize($size);
            //@todo colorize
            $this->blueStyle->writeln("$file ($formattedSize)");
        }

        $this->blueStyle->newLine();

        return $this;
    }

    /**
     * @return array
     */
    public function returnCounters() : array
    {
        return [
            $this->duplicatedFilesSize,
            0,
            0,
        ];
    }
}
