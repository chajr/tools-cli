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
     * @var array
     */
    protected $allRules = [
        'regExp' => false,
        'time' => false,
        'size' => false,
        'extension' => false,
    ];

    /**
     * @var bool
     */
    protected $isValid = false;

    /**
     * @param array $config
     * @param \SplFileInfo $fileInfo
     * @throws \Exception
     */
    public function __construct(array $config, \SplFileInfo $fileInfo)
    {
        $this->rules = \array_merge($this->allRules, $config['rules']);
        $this->fileInfo = $fileInfo;

        $this->validate();
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * @throws \Exception
     */
    public function validate(): void
    {
        foreach ($this->rules as $name => $rule) {
            if (!$rule) {
                continue;
            }

            $methodName = $name . 'Rule';
            $status = $this->$methodName($rule);

            if ($status === null) {
                continue;
            }

            $this->isValid &= $status;
        }
    }

    /**
     * @param string $rule
     * @return bool
     */
    protected function regExpRule(string $rule): bool
    {
        return !!\preg_match($rule, $this->fileInfo->getFilename());
    }

    /**
     * @param array $rule
     * @return bool|null
     * @throws \Exception
     */
    protected function timeRule(array $rule):? bool
    {
        switch ($rule['type']) {
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
                return null;
        }

        $fileStamp = (new \DateTime())->setTimestamp($fileStamp);
        $timeDiff = new \DateTime($rule['date']);

        $stampDiff = $timeDiff->getTimestamp();
        $stampFile = $fileStamp->getTimestamp();

        return !($stampDiff < $stampFile);
    }

    /**
     * @param string $rule
     * @return bool|null
     */
    protected function sizeRule(string $rule):? bool
    {
        $valid = \preg_match('/([><]?)([\d]+)([kmgtpKMGTP])/', $rule, $matches);

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

            return $sizeRuleValid;
        }

        return null;
    }

    /**
     * @param string|array $rule
     * @return bool
     */
    protected function extensionRule($rule): bool
    {
        return \in_array($this->fileInfo->getExtension(), $rule, true);
    }
}
