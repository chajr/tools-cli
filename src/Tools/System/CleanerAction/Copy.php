<?php

namespace ToolsCli\Tools\System\CleanerAction;

use BlueFilesystem\StaticObjects\Fs;
use BlueConsole\Style;
use BlueFilesystem\StaticObjects\Structure;
use BlueRegister\Register;

class Copy implements Action
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
                throw new \InvalidArgumentException('Destination not found: ' . $path);
            }

            $suffix = $configList['params']['file_suffix'] ?? '';

            $out = Fs::copy(
                $path,
                $configList['params']['destination'] . DIRECTORY_SEPARATOR . $fileInfo->getFilename() . $suffix
            );

            if (!Fs::validateComplexOutput($out)) {
                $style->errorMessage("Unable to copy file: <fg=yellow;options=bold>{$fileInfo->getFilename()}</>");
            }

            $style->okMessage("File <fg=yellow;options=bold>{$fileInfo->getFilename()}</> copied successfully");
        };
    }
}
