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

        if ($branch === 'develop') {
            exec('git add -A');
            exec('git commit -m "Updated version form: ' . $previousVersion . ' to: ' . $currentVersion . '"');
            exec('git push origin develop');
            exec('git checkout master');
        }
        
        //checkout
        
        /**
         * check if current branch is develop
        git push origin develop
        checkout to master
        git merge develop
        git push origin master
        git tag $TAG
        git push --tags
        git checkout develop
         */
        $this->blueStyle->note('Version changed form: ' . $previousVersion . ' to: ' . $currentVersion);
    }
}
