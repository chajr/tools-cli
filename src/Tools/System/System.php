<?php

namespace ToolsCli\Tools\System;

use Symfony\Component\Console\Command\Command;

class System extends Command
{
    public function __construct()
    {
        parent::__construct();
        echo __CLASS__ . "\n";
    }
    //suspend
    //hiberante
    //etc
    //systemctl suspend -i //after period
    //shutdown -h $time
}
