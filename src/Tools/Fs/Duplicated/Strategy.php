<?php

namespace ToolsCli\Tools\Fs\Duplicated;

use ToolsCli\Tools\Fs\DuplicatedFilesTool;

interface Strategy
{
    public function __construct(DuplicatedFilesTool $dft);
    public function checkByHash(array $hashes);
}
