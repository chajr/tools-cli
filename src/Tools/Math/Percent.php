<?php

namespace ToolsCli\Tools\Math;

use Symfony\Component\Console\Command\Command;

class Percent extends Command
{
    public function __construct()
    {
        parent::__construct();
        echo __CLASS__ . "\n";
    }
}
