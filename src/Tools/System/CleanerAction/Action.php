<?php

namespace ToolsCli\Tools\System\CleanerAction;

use ToolsCli\Console\Display\Style;

interface Action
{
    public function __construct(array $rules, Style $style);
    public function getCallback(): callable;
}
