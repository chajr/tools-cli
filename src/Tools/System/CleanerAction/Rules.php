<?php
/**
 * @author Michał Adamiak <michal.adamiak@lizardmedia.pl>
 * @copyright Copyright (C) 2019 Lizard Media (http://lizardmedia.pl)
 */

namespace ToolsCli\Tools\System\CleanerAction;

use BlueConsole\Style;

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
     * @var Style
     */
    protected $blueStyle;

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
     * @param Style $style
     */
    public function __construct(array $config, \SplFileInfo $fileInfo, Style $style)
    {
        $this->rules = \array_merge($this->allRules, $config['rules']);
        $this->fileInfo = $fileInfo;
        $this->blueStyle = $style;

        $this->validate();
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function validate(): void
    {
        $status = false;

        foreach ($this->rules as $name => $rule) {
            if (!$rule) {
                continue;
            }

            $methodName = $name . 'Rule';

            try {
                $status = $this->$methodName($rule);
            } catch (\Throwable $exception) {
                $this->blueStyle->errorMessage($exception->getMessage());
                break;
            }

            if ($status === false) {
                break;
            }
        }

        $this->isValid = $status;
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
     * @return bool
     * @throws \Exception
     * @throws CleanerRuleException
     */
    protected function timeRule(array $rule): bool
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
                throw new CleanerRuleException('Illegal time type: ' . $rule['type']);
        }

        $fileStamp = (new \DateTime())->setTimestamp($fileStamp);
        $timeDiff = new \DateTime($rule['date']);

        $stampDiff = $timeDiff->getTimestamp();
        $stampFile = $fileStamp->getTimestamp();

        return !($stampDiff < $stampFile);
    }

    /**
     * @param string $rule
     * @return bool
     * @throws CleanerRuleException
     */
    protected function sizeRule(string $rule): bool
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

        throw new CleanerRuleException('Invalid size type: ' . $rule);
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
