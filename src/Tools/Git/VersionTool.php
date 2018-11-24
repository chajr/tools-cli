<?php

namespace ToolsCli\Tools\Git;

use Symfony\Component\Console\{
    Input\InputInterface,
    Output\OutputInterface,
    Helper\FormatterHelper,
    Input\InputArgument,
};
use ToolsCli\Console\{
    Command,
    Alias,
};
use BlueFilesystem\Fs;
use BlueRegister\{
    Register, RegisterException
};
use BlueConsole\Style;

class VersionTool extends Command
{
    /**
     * @var Register
     */
    protected $register;

    /**
     * @var string
     */
    protected $commandName = 'git:version';

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

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
        $this->setName($this->commandName)
            ->setDescription($this->getAlias() . 'Automatic lib/app version update with git push.')
            ->setHelp('');

        $this->addArgument(
            'version',
            InputArgument::REQUIRED,
            'version that will be updated'
        );
    }

    /**
     * @param string $shellCommand
     * @param string $display
     * @return $this
     */
    protected function exec(string $shellCommand, string $display = '') : self
    {
        $out = [];

        $this->blueStyle->genericBlock($shellCommand, 'green', 'command');
        exec($shellCommand . ' 2>&1', $out);

        switch ($display) {
            case 'show':
                $this->blueStyle->formatBlock($out, 'info');
                break;

            default:
                break;
        }

        return $this;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->formatter = $this->register->factory(FormatterHelper::class);
            $this->blueStyle = $this->register->factory(Style::class, [$input, $output, $this->formatter]);
        } catch (RegisterException $exception) {
            throw new \Exception('RegisterException: ' . $exception->getMessage());
        }

        $dir = getcwd();

        if (!Fs::exist($dir . '/composer.json')) {
            throw new \Exception('Missing composer.json file.');
        }

        $composer = json_decode(
            file_get_contents($dir . '/composer.json'),
            true
        );

        if (!($composer['version'] ?? false)) {
            throw new \Exception('Missing version in composer.json file.');
            /** @todo add option to add version in composer */
        }

        $previousVersion = $composer['version'];
        $currentVersion = $input->getArgument('version');

        $changelog = file_get_contents($dir . '/doc/CHANGELOG.md');
        if (!preg_match("/^## $currentVersion/", $changelog)) {
            throw new \Exception('Missing version entry in: ' . '/doc/CHANGELOG.md');
        }

        $composer['version'] = $currentVersion;
        $success = file_put_contents(
            $dir . '/composer.json',
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        if (!$success) {
            throw new \Exception('Unable to write ' . $dir . '/composer.json file.');
        }

        $branch = exec('git rev-parse --abbrev-ref HEAD');
        $this->exec('git add -A', 'show')
            ->commit($previousVersion, $currentVersion, 'show');

        if ($branch === 'develop') {
            $this->exec('git push origin develop', 'show')
                ->exec('git checkout master', 'show')
                ->exec('git merge develop', 'show')
                ->exec('git push origin master', 'show');
        } elseif ($branch === 'master') {
            $this->exec('git push origin master', 'show');
        }

        $this->exec('git tag ' . $currentVersion, 'show')
            ->exec('git push --tags', 'show')
            ->exec('git checkout develop', 'show');

        $this->blueStyle->note('Version changed form: ' . $previousVersion . ' to: ' . $currentVersion);
    }

    /**
     * @param string $previousVersion
     * @param string $currentVersion
     * @param string $display
     * @return VersionTool
     */
    protected function commit(string $previousVersion, string $currentVersion, string $display) : self
    {
        return $this->exec(
            'git commit -m "Updated version form: ' . $previousVersion . ' to: ' . $currentVersion . '"',
            $display
        );
    }
}
