<?php

namespace ToolsCli\Console\Display;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Style extends SymfonyStyle
{
    /**
     * @var \Symfony\Component\Console\Helper\FormatterHelper
     */
    protected $formatter;

    /**
     * @var int
     */
    protected $align = 20;

    /**
     * Style constructor.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param Command $command
     */
    public function __construct(InputInterface $input, OutputInterface $output, Command $command)
    {
        $this->formatter = $command->getHelper('formatter');

        parent::__construct($input, $output);
    }

    /**
     * @param int $align
     * @return $this
     */
    public function setAlign($align) : self
    {
        $this->align = $align;

        return $this;
    }

    /**
     * @return int
     */
    public function getAlign() : int
    {
        return $this->align;
    }

    /**
     * @param string $section
     * @param string $message
     * @param string $style
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function formatSection($section, $message, $style = 'info') : self
    {
        $this->writeln(
            $this->formatter->formatSection(
                $section,
                $message,
                $style
            )
        );

        return $this;
    }

    /**
     * @param string|array $messages
     * @param string $style
     * @param bool $large
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function formatBlock($messages, $style, $large = false) : self
    {
        $this->writeln(
            $this->formatter->formatBlock(
                $messages,
                $style,
                $large
            )
        );

        return $this;
    }

    /**
     * @param array $message
     * @throws \InvalidArgumentException
     * @return $this
     */
    public function errorLine(array $message) : self
    {
        $this->writeln(
            $this->formatBlock($message, 'error')
        );

        return $this;
    }

    /**
     * @param string|int $strLength
     * @param int $align
     * @return string
     */
    public function align($strLength, $align) : string
    {
        if (\is_string($strLength)) {
            $strLength = mb_strlen($strLength);
        }

        $newAlign = ' ';
        $spaces = $align - $strLength;

        for ($i = 1; $i <= $spaces; $i++) {
            $newAlign .= ' ';
        }

        return $newAlign;
    }

    /**
     * @param $message
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function okMessage($message) : self
    {
        $alignment = $this->align(4, $this->align);
        $this->write('<info>[OK]</info>');
        $this->write($alignment);
        $this->writeln($message);

        return $this;
    }

    /**
     * @param $message
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function errorMessage($message) : self
    {
        $alignment = $this->align(7, $this->align);
        $this->write('<fg=red>[ERROR]</>');
        $this->write($alignment);
        $this->writeln($message);

        return $this;
    }

    /**
     * @param string $message
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function warningMessage(string $message) : self
    {
        $alignment = $this->align(9, $this->align);
        $this->write('<comment>[WARNING]</comment>');
        $this->write($alignment);
        $this->writeln($message);

        return $this;
    }

    /**
     * @param string $message
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function infoMessage(string $message) : self
    {
        $alignment = $this->align(6, $this->align);
        $this->write('<fg=blue>[INFO]</>');
        $this->write($alignment);
        $this->writeln($message);

        return $this;
    }

    public function message($message, $color, $label) : self
    {
        if ($color) {

        }

        if ($label) {

        }

        $this->writeln($message);

        return $this;
    }

    /**
     * @param string $message
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function note($message) : self
    {
        return $this->genericBlock($message, 'blue', 'note');
    }

    /**
     * @param string $message
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function caution($message) : self
    {
        return $this->genericBlock($message, 'magenta', 'caution');
    }

    /**
     * @param string $message
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function success($message) : self
    {
        return $this->genericBlock($message, 'green', 'success');
    }

    /**
     * @param string $message
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function warning($message) : self
    {
        return $this->genericBlock($message, 'yellow', 'warning');
    }

    /**
     * @param string $message
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function error($message) : self
    {
        return $this->genericBlock($message, 'red', 'error');
    }

    /**
     * @param string $message
     * @param string $background
     * @param string $type
     * @param int $length
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function genericBlock($message, $background, $type, $length = 100) : self
    {
        $type = strtoupper($type);
        $alignment = $this->align(0, $length);
        $alignmentMessage = $this->align($message, $length - (mb_strlen($type) + 5));

        $this->writeln("<bg=$background>$alignment</>");
        $this->writeln("<fg=white;bg=$background>  [$type] $message$alignmentMessage</>");
        $this->writeln("<bg=$background>$alignment</>");
        $this->newLine();

        return $this;
    }
}
