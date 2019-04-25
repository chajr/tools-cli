<?php

namespace ToolsCli\Tools\System\CleanerAction;

use BlueConsole\Style;
use BlueRegister\Register;

interface Action
{
    /**
     * @param array $rules
     * @param Style $style
     * @param Register $register
     */
    public function __construct(array $rules, Style $style, Register $register);

    /**
     * @return callable
     */
    public function getCallback(): callable;
}
