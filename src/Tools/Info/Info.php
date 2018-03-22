<?php

namespace ToolsCli\Tools\Info;

use Symfony\Component\Console\Command\Command;

class Info extends Command
{
    public function __construct()
    {
        parent::__construct();
        echo __CLASS__ . "\n";
    }
}
