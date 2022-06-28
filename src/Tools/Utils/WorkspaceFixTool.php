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
use Spatie\ArrayToXml\ArrayToXml;
use BlueData\Data\Xml;

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
        }

        $path = $input->getArgument('path');
        $message = 'Execution time: ' . (new \DateTime)->format('c');

        if ($output->isVerbose()) {
            $this->blueStyle->infoMessage($message);
            $this->blueStyle->infoMessage('Workspace fixer begin.');
        } else {
            $output->writeln($message);
        }

        if (!\file_exists($path)) {
            throw new \InvalidArgumentException("Workspace file $path is missing.");
        }

        if ($output->isVerbose()) {
            $this->blueStyle->infoMessage('Read file.');
        }

        $data = \file_get_contents($path);

        if ($output->isVerbose()) {
            $this->blueStyle->infoMessage('Process config file.');
        }

        $loadedXml = \simplexml_load_string($data);
        $jsonXml = \json_encode($loadedXml);
        $jsonData = \json_decode($jsonXml, true);

        $comment = null;
        $index = 0;

        if ($output->isVerbose()) {
            $this->blueStyle->infoMessage('Search for broken component.');
        }

        foreach ($jsonData['component'] as $index => $element) {
            if (
                isset($element['@attributes']['name'])
                && $element['@attributes']['name'] === 'ChangeListManager'
            ) {
                $comment = $element['list']['@attributes']['comment'] ?? '';
                break;
            }
        }

        if (!$comment) {
            $message = 'Element already empty.';

            if ($output->isVerbose()) {
                $this->blueStyle->infoMessage($message);
            } else {
                $output->writeln($message);
            }

            return;
        }

        if ($output->isVerbose()) {
            $this->blueStyle->infoMessage('Fixing broken component.');
        }

        $jsonData['component'][$index]['list']['@attributes']['comment'] = '';

        if ($output->isVerbose()) {
            $this->blueStyle->infoMessage('Save config file.');
        }

        $this->convertAndSave($jsonData, $path, $output->isVerbose());
    }

    /**
     * @param array $data
     * @param string $path
     * @param bool $isVerbose
     */
    protected function convertAndSave(array $data, string $path, bool $isVerbose): void
    {
        $baseXml =  ArrayToXml::convert($data, 'project');

        $xml = new Xml;
        $xml->formatOutput = true;
        $xml->loadXML($baseXml);
        $xml->saveXmlFile($path);

        if ($xml->hasErrors() && $isVerbose) {
            $this->blueStyle->errorMessage('Unable to save xml file: ' . $xml->getError());
            return;
        }

        if ($isVerbose) {
            $this->blueStyle->okMessage('File changed & saved successfully');
        }
    }
}
