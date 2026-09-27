<?php

namespace ToolsCli\Tools\Utils;

use Symfony\Component\Console\{
    Input\InputInterface,
    Input\InputArgument,
    Input\InputOption,
    Output\OutputInterface,
    Helper\FormatterHelper,
};
use BlueRegister\{
    Register, RegisterException
};
use ToolsCli\Console\{
    Command,
    Alias,
};
use BlueConsole\Style;

class MusicDownloaderTool extends Command
{
    /**
     * @var Register
     */
    protected $register;

    /**
     * @var array | bool
     */
    protected $urls;

    /**
     * @var Style
     */
    protected $blueStyle;

    /**
     * @var FormatterHelper
     */
    protected $formatter;

    public const SUPPORTED_COOKIES = [
        'brave',
        'chrome',
        'chromium',
        'edge',
        'firefox',
        'opera',
        'safari',
        'vivaldi',
        'whale',
    ];

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

    protected function configure(): void
    {
        $this->setName('utils:music-downloader')
            ->setDescription('Download music from url-s given in input file.')
            ->setHelp('');

        $this->addOption(
            'input-file',
            'i',
            InputOption::VALUE_OPTIONAL,
            'Path to the input file containing URLs'
        );

        $this->addOption(
            'video',
            'w',
            null,
            'Download video instead of music'
        );

        $this->addOption(
            'url',
            'u',
            InputOption::VALUE_OPTIONAL,
            'Valid url to download music/video'
        );

        $this->addOption(
            'destination',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Path to the destination directory, default current directory'
        );

        $this->addOption(
            'cookie',
            'c',
            InputOption::VALUE_REQUIRED,
            'Get cookies from browser (' . \implode(', ', self::SUPPORTED_COOKIES) . ')'
        );

        $this->addOption(
            'debug',
            'D',
            null,
            'Show full output returned by youtube-dl'
        );

        //show progress from single file?
        //show progress
        //in future add threads
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->formatter = $this->register->factory(FormatterHelper::class);
            $this->blueStyle = $this->register->factory(Style::class, [$input, $output, $this->formatter]);
        } catch (RegisterException $exception) {
            throw new \UnexpectedValueException('RegisterException: ' . $exception->getMessage());
        }

        $this->blueStyle->title('Download music/video');

        $destination = $input->getOption('destination') ?: \getcwd();
        $this->checkOptions($input, $destination);
        $urlProces = $input->getOption('url') ?: $this->urls;
        $downloadCount = 0;
        $cookie = '';
        $mp3 = '-x --audio-format mp3';

        if (\is_string($urlProces)) {
            $urlProces = [$urlProces];
        }
        $allDownloads = \count($urlProces);

        if ($input->getOption('video')) {
            $mp3 = '';
        }

        if ($input->getOption('cookie')) {
            if (!\in_array($input->getOption('cookie'), self::SUPPORTED_COOKIES, true)) {
                throw new \InvalidArgumentException(
                    'Invalid cookie option. Supported options are: ' . \implode(', ', self::SUPPORTED_COOKIES)
                );
            }

            $cookie = '--cookies-from-browser ' . $input->getOption('cookie');
        }

        $this->blueStyle->infoMessage("Process all $allDownloads urls.");
        foreach ($urlProces as $url) {
            $this->blueStyle->infoMessage('Downloading from ' . $url);

            \exec(
                "ydl --no-playlist $cookie --break-on-existing -o '%(title)s.%(ext)s' -P $destination $mp3 $url 2>&1",
                $output,
                $resultCode
            );

            if ($input->getOption('debug')) {
                $this->blueStyle->block($output);
            }

            if ($resultCode !== 0) {
                $this->blueStyle->errorMessage('Failed to download from ' . $url);
                $this->blueStyle->errorLine($output);

                unset($output);
                continue;
            }

            $downloadCount++;
            $this->blueStyle->okMessage('Downloaded'); //add title
            $this->blueStyle->infoMessage("$downloadCount of $allDownloads");
            unset($output);
        }

        $this->blueStyle->okMessage("Done $downloadCount downloads of $allDownloads.");

        return self::SUCCESS;
    }

    /**
     * @param InputInterface $input
     * @param string $destination
     * @return void
     * @throws \Exception
     */
    protected function checkOptions(InputInterface $input, string $destination): void
    {
        $this->blueStyle->infoMessage('Checking options.');

        if (!\is_dir($destination) && !\mkdir($destination, 0777, true) && !\is_dir($destination)) {
            throw new \RuntimeException(\sprintf('Directory "%s" was not created', $destination));
        }

        if (!$input->getOption('url') && !$input->getOption('input-file')) {
            throw new \InvalidArgumentException('You must provide either URL or input-file option.');
        }

        if ($input->getOption('url') && $input->getOption('input-file')) {
            throw new \InvalidArgumentException('Provide only on of following options, URL or input-file.');
        }

        if ($input->getOption('input-file')) {
            $this->urls = \file($input->getOption('input-file'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($this->urls === false) {
                throw new \RuntimeException('Failed to read the input file.');
            }
        }
    }
}
