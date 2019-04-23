<?php

namespace ToolsCli\Tools\System\CleanerAction;

use BlueFilesystem\StaticObjects\{
    Fs,
    Structure
};
use BlueConsole\Style;

class Move implements Action
{
    /**
     * @var array
     */
    protected $rules;

    /**
     * @var Style
     */
    protected $blueStyle;

    /**
     * @param array $rules
     * @param Style $blueStyle
     */
    public function __construct(array $rules, Style $blueStyle)
    {
        $this->rules = $rules;
        $this->blueStyle = $blueStyle;
    }

    /**
     * @todo move rules to some generic place
     * @return callable
     */
    public function getCallback(): callable
    {
        $rules = $this->rules;
        $style= $this->blueStyle;

        return function (\SplFileInfo $fileInfo, string $path) use ($rules, $style) {
            $regexp = $rules['regexp'];
            $found = \preg_match($regexp, $fileInfo->getFilename());

            if (!$found) {
                return;
            }

            switch ($rules['date-type']) {
                case 'create':
                    $fileStamp = $fileInfo->getCTime();
                    break;

                case 'access':
                    $fileStamp = $fileInfo->getATime();
                    break;

                case 'modify':
                    $fileStamp = $fileInfo->getMTime();
                    break;

                default:
                    $fileStamp = false;
                    break;
            }

            if ($fileStamp) {
                $fileStamp = (new \DateTime())->setTimestamp($fileStamp);
                $timeDiff = new \DateTime($rules['date-time']);

                $stampDiff = $timeDiff->getTimestamp();
                $stampFile = $fileStamp->getTimestamp();

                if ($stampDiff < $stampFile) {
                    return;
                }
            }

            $valid = \preg_match('/([><]?)([\d]+)([kmgtpKMGTP])/', $rules['size'], $matches);

            if ($valid) {
                $sizeSuffix = \strtolower($matches[3]);
                $reverse = array_flip(self::SIZE_SUFFIX);

                $valCalculated = $matches[2] * 1024 ** ($reverse[$sizeSuffix] +1);

                switch ($matches[1]) {
                    case '>':
                        $sizeRuleValid = $fileInfo->getSize() > $valCalculated;
                        break;

                    case '<':
                        $sizeRuleValid = $fileInfo->getSize() < $valCalculated;
                        break;

                    default:
                        $sizeRuleValid = $fileInfo->getSize() === $valCalculated;
                        break;
                }

                if (!$sizeRuleValid) {
                    return;
                }
            }

            if (!Structure::exist($rules['destination'])) {
                throw new \Exception('Destination not found: ' . $path);
            }

            $out = Fs::move($path, $rules['destination'] . DIRECTORY_SEPARATOR . $fileInfo->getFilename());

            if (!Fs::validateComplexOutput($out)) {
                $style->errorMessage("Unable to move file: <fg=yellow;options=bold>{$fileInfo->getFilename()}</>");
            }

            $style->okMessage("File <fg=yellow;options=bold>{$fileInfo->getFilename()}</> moved successfully");
        };
    }
}
