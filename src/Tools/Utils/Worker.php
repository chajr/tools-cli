<?php

namespace ToolsCli\Tools\Utils;

use Symfony\Component\Console\Command\Command;

class Worker extends Command
{
    public function __construct()
    {
        parent::__construct();
        echo __CLASS__ . "\n";
    }
    //command
    //worker sleep (bigger than 0.1s)
}
