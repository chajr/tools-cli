<?php

namespace ToolsCli\Tools\Fs\Duplicated;

use ToolsCli\Tools\Fs\DuplicatedFilesTool;
use BlueConsole\MultiSelect;

class Interactive
{
    public function __construct(DuplicatedFilesTool $dft)
    {
            $this->blueStyle->writeln('Deleted files: ' . $this->deleteCounter);
            $this->blueStyle->writeln('Deleted files size: ' . Formats::dataSize($this->deleteSizeCounter));
            $this->blueStyle->newLine();
    }

    protected function checkByHash(array $hashes) : self
    {
        $multiselect = (new MultiSelect($this->blueStyle))->toggleShowInfo(false);

        foreach ($hashes as $hash) {
            if (\count($hash) > 1) {
                $this->blueStyle->writeln('Duplications:');

                    $this->interactive(
                        $hash,
                        $multiselect
                    );

                $this->blueStyle->newLine();
            }
        }

        return $this;
    }

    /**
     * @param array $hash
     * @param MultiSelect $multiselect
     * @return $this
     */
    protected function interactive(
        array $hash,
        MultiSelect $multiselect
    ) : self {
        $hashWithSize = [];

        foreach ($hash as $file) {
            $this->duplicatedFiles++;
            $size = filesize($file);
            $this->duplicatedFilesSize += $size;

            $formattedSize = Formats::dataSize($size);
            $hashWithSize[] = "$file (<info>$formattedSize</>)";
        }

        //@todo show deleted file size
        $selected = $multiselect->renderMultiSelect($hashWithSize);

        if ($selected) {
            foreach (array_keys($selected) as $idToDelete) {
                //delete process
                $this->deleteSizeCounter += filesize($hash[$idToDelete]);
                $this->blueStyle->warningMessage('Removing: ' . $hash[$idToDelete]);
                $out = Fs::delete($hash[$idToDelete]);

                if (reset($out)) {
                    $this->blueStyle->okMessage('Removed success: ' . $hash[$idToDelete]);
                    $this->deleteCounter++;
                } else {
                    $this->blueStyle->errorMessage('Removed fail: ' . $hash[$idToDelete]);
                }
            }
        }

        return $this;
    }
}
