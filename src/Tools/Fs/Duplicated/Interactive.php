<?php

namespace ToolsCli\Tools\Fs\Duplicated;

use ToolsCli\Tools\Fs\DuplicatedFilesTool;
use BlueConsole\MultiSelect;
use BlueData\Data\Formats;
use BlueFilesystem\StaticObjects\Fs;

class Interactive implements Strategy
{
    /**
     * @var \BlueConsole\Style
     */
    protected $blueStyle;

    /**
     * @var MultiSelect
     */
    protected $multiselect;

    protected $deleteCounter = 0;
    protected $deleteSizeCounter = 0;
    protected $duplicatedFiles = 0;
    protected $duplicatedFilesSize = 0;

    /**
     * @param DuplicatedFilesTool $dft
     */
    public function __construct(DuplicatedFilesTool $dft)
    {
        $this->blueStyle = $dft->getBlueStyle();
        $this->multiselect = (new MultiSelect($this->blueStyle))->toggleShowInfo(false);

        $this->blueStyle->writeln('Deleted files: ' . $this->deleteCounter);
        $this->blueStyle->writeln('Deleted files size: ' . Formats::dataSize($this->deleteSizeCounter));
        $this->blueStyle->newLine();
    }

    /**
     * @param array $hash
     * @return Interactive
     */
    public function checkByHash(array $hash) : Strategy
    {
        $this->blueStyle->writeln('Duplications:');

        $this->interactive($hash, $this->multiselect);

        $this->blueStyle->newLine();

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
            $this->deleteCounter,
            $this->deleteSizeCounter,
        ];
    }

    /**
     * @param array $hash
     * @param MultiSelect $multiselect
     * @return $this
     */
    protected function interactive(array $hash, MultiSelect $multiselect) : self
    {
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
            $this->processRemoving($selected, $hash);
        }

        return $this;
    }

    /**
     * @param array $selected
     * @param array $hash
     */
    protected function processRemoving(array $selected, array $hash) : void
    {
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
}
