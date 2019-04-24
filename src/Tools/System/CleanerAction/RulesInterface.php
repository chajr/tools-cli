<?php

namespace ToolsCli\Tools\System\CleanerAction;

interface RulesInterface
{
    /**
     * @param array $rules
     * @param \SplFileInfo $fileInfo
     */
    public function __construct(array $rules, \SplFileInfo $fileInfo);

    /**
     * @return bool
     */
    public function isValid(): bool;
}
