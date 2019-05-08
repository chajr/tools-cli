<?php

namespace ToolsCli\Tools\Utils;

use Symfony\Component\Console\{
    Input\InputInterface,
    Output\OutputInterface,
    Helper\FormatterHelper,
    Input\InputArgument,
};
use BlueRegister\{
    Register, RegisterException
};
use ToolsCli\Console\{
    Command,
    Alias,
};
use BlueConsole\Style;

class WorkspaceFixTool extends Command
{
    /**
     * @var Register
     */
    protected $register;

    /**
     * @var Style
     */
    protected $blueStyle;

    /**
     * @var FormatterHelper
     */
    protected $formatter;

    /**
     * @param string $name
     * @param Alias $alias
     * @param Register $register
     */
    public function __construct(string $name, Alias $alias, Register $register)
    {
        $this->register = $register;
        parent::__construct($name, $alias);
    }

    protected function configure() : void
    {
        $this->setName('utils:workspace-fix')
            ->setDescription('')
            ->setHelp('');

        $this->addArgument(
            'path',
            InputArgument::REQUIRED,
            'storm workspace main file'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        if ($output->isVerbose()) {
            try {
                $this->formatter = $this->register->factory(FormatterHelper::class);
                $this->blueStyle = $this->register->factory(Style::class, [$input, $output, $this->formatter]);
            } catch (RegisterException $exception) {
                throw new \UnexpectedValueException('RegisterException: ' . $exception->getMessage());
            }

            $this->blueStyle->infoMessage('Workspace fixer begin.');
        }

        $path = $input->getArgument('path');

        if (!\file_exists($path)) {
            throw new \InvalidArgumentException("Workspace file $path is missing.");
        }

        $data = \file_get_contents($path);
        $loadedXml = simplexml_load_string($data);
        $jsonXml = json_encode($loadedXml);
        $jsonData = json_decode($jsonXml, true);

        $comment = $jsonData['component'][0]['list']['@attributes']['comment'] ?? '';

        if (!$comment) {
            $message = 'Element already empty.';

            if ($output->isVerbose()) {
                $this->blueStyle->infoMessage($message);
            } else {
                $output->writeln($message);
            }

            return;
        }

        $jsonData['component'][0]['list']['@attributes']['comment'] = '';

        dump($jsonData);
    }
}
