<?php

namespace ToolsCli\Tools\Fs\Duplicated;

class NoInteractive
{
    public function __construct()
    {
        
    }

    /**
     * @param array $hashes
     * @return $this
     */
    protected function checkByHash(array $hashes) : self
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
}
