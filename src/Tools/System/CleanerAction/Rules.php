<?php
/**
 * @author Michał Adamiak <michal.adamiak@lizardmedia.pl>
 * @copyright Copyright (C) 2019 Lizard Media (http://lizardmedia.pl)
 */

namespace ToolsCli\Tools\System\CleanerAction;

class Rules
{
    public function __construct(array $rules)
    {
        $regexp = $rules['regexp'];
        $found = \preg_match($regexp, $fileInfo->getFilename());
    }

    public function isValid()
    {
        $rules = true;

        $rules &= $this->regExpRule();
        $rules &= $this->timeRule();
        $rules &= $this->sizeRule();

        return !!$rules;
    }

    protected function regExpRule(): bool
    {
        
    }

    protected function timeRule(): bool
    {
        
    }

    protected function sizeRule(): bool
    {
        
    }
}
