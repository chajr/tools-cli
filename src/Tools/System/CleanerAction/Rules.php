<?php
/**
 * @author Michał Adamiak <michal.adamiak@lizardmedia.pl>
 * @copyright Copyright (C) 2019 Lizard Media (http://lizardmedia.pl)
 */

namespace ToolsCli\Tools\System\CleanerAction;

class Rules implements RulesInterface
{
    public const SIZE_SUFFIX = ['k', 'm', 'g', 't', 'p'];

    /**
     * @var array
     */
    protected $rules;

    /**
     * @var \SplFileInfo
     */
    protected $fileInfo;

    /**
     * @param array $rules
     * @param \SplFileInfo $fileInfo
     */
    public function __construct(array $rules, \SplFileInfo $fileInfo)
    {
        $this->rules = $rules;
        $this->fileInfo = $fileInfo;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isValid(): bool
    {
        $status = true;

        $status &= $this->regExpRule();
        $status &= $this->timeRule();
        $status &= $this->sizeRule();

        return $status;
    }

    /**
     * @return bool
     */
    protected function regExpRule(): bool
    {
        return !!\preg_match($this->rules['regexp'], $this->fileInfo->getFilename());
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function timeRule(): bool
    {
        switch ($this->rules['date-type']) {
            case 'create':
                $fileStamp = $this->fileInfo->getCTime();
                break;

            case 'access':
                $fileStamp = $this->fileInfo->getATime();
                break;

            case 'modify':
                $fileStamp = $this->fileInfo->getMTime();
                break;

            default:
                $fileStamp = false;
                break;
        }

        if ($fileStamp) {
            $fileStamp = (new \DateTime())->setTimestamp($fileStamp);
            $timeDiff = new \DateTime($this->rules['date-time']);

            $stampDiff = $timeDiff->getTimestamp();
            $stampFile = $fileStamp->getTimestamp();

            if ($stampDiff < $stampFile) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function sizeRule(): bool
    {
        $valid = \preg_match('/([><]?)([\d]+)([kmgtpKMGTP])/', $this->rules['size'], $matches);

        if ($valid) {
            $sizeSuffix = \strtolower($matches[3]);
            $reverse = array_flip(self::SIZE_SUFFIX);

            $valCalculated = $matches[2] * 1024 ** ($reverse[$sizeSuffix] +1);

            switch ($matches[1]) {
                case '>':
                    $sizeRuleValid = $this->fileInfo->getSize() > $valCalculated;
                    break;

                case '<':
                    $sizeRuleValid = $this->fileInfo->getSize() < $valCalculated;
                    break;

                default:
                    $sizeRuleValid = $this->fileInfo->getSize() === $valCalculated;
                    break;
            }

            if (!$sizeRuleValid) {
                return false;
            }
        }

        return true;
    }
}
