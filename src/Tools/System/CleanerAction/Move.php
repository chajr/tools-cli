<?php

namespace ToolsCli\Tools\System\CleanerAction;

use BlueFilesystem\StaticObjects\{
    Fs,
    Structure
};
use BlueConsole\Style;
use BlueRegister\Register;

class Move implements Action
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var Style
     */
    protected $blueStyle;

    /**
     * @var Register
     */
    protected $register;

    /**
     * @param array $config
     * @param Style $blueStyle
     * @param Register $register
     */
    public function __construct(array $config, Style $blueStyle, Register $register)
    {
        $this->config = $config;
        $this->blueStyle = $blueStyle;
        $this->register = $register;
    }

    /**
     * @return callable
     */
    public function getCallback(): callable
    {
        $configList = $this->config;
        $style = $this->blueStyle;
        $registerObject = $this->register;

        return function (\SplFileInfo $fileInfo, string $path) use ($configList, $style, $registerObject) {
            $rule = $registerObject->factory(Rules::class, [$configList, $fileInfo, $style]);

            if (!$rule->isValid()) {
                return;
            }

            if (!Structure::exist($configList['params']['destination'])) {
                throw new \InvalidArgumentException('Destination not found: ' . $configList['params']['destination']);
            }

            $out = Fs::move(
                $path,
                $configList['params']['destination'] . DIRECTORY_SEPARATOR . $fileInfo->getFilename()
            );

            if (!Fs::validateComplexOutput($out)) {
                $style->errorMessage("Unable to move file: <fg=yellow;options=bold>{$fileInfo->getFilename()}</>");
            }

            $style->okMessage("File <fg=yellow;options=bold>{$fileInfo->getFilename()}</> moved successfully");
        };
    }
}
