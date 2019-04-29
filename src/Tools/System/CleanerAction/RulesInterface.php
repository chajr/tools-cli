<?php

namespace ToolsCli\Tools\System\CleanerAction;

use BlueConsole\Style;

interface RulesInterface
{
    /**
     * @param array $rules
     * @param \SplFileInfo $fileInfo
     * @param Style $style
     */
    public function __construct(array $rules, \SplFileInfo $fileInfo, Style $style);

    /**
     * @return bool
     */
    public function isValid(): bool;
}
