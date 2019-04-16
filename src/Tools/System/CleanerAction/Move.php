<?php

namespace ToolsCli\Tools\System\CleanerAction;

use BlueFilesystem\StaticObjects\{
    Fs,
    Structure
};
use ToolsCli\Console\Display\Style;

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
     * @return callable
     */
    public function getCallback(): callable
    {
        $rules = $this->rules;
        $style= $this->blueStyle;

        return function (\SplFileInfo $fileInfo, string $path) use ($rules, $style) {
            $regexp = $rules['regexp'];
            $found = \preg_match($regexp, $fileInfo->getFilename());

            if ($found) {
                if (!Structure::exist($rules['destination'])) {
                    throw new \Exception('Destination not found: ' . $path);
                }

                $out = Fs::move($path, $rules['destination'] . DIRECTORY_SEPARATOR . $fileInfo->getFilename());

                if (!Fs::validateComplexOutput($out)) {
                    $style->error('Unable to move content: ' . \implode(PHP_EOL, $out));
                }
            }
        };
    }
}
