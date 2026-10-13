<?php

declare(strict_types=1);

namespace ToolsCli\Tools\Azure;

use BlueConsole\MultiSelect;
use BlueConsole\Style;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * Register resource providers in subscription. Only adds, never unregisters.
 * Requires logged in az cli (az login).
 */
class ProvidersTool extends Command
{
    protected const REGISTERED = ['Registered', 'Registering'];
    protected const SPINNER = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    protected function configure(): void
    {
        $this->setName('azure:providers')
            ->setDescription('Select resource providers to register in subscription (az login required).')
            ->addArgument('subscription', InputArgument::REQUIRED, 'Subscription name or id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new Style($input, $output, new FormatterHelper());
        [$id, $name] = $this->subscription($input->getArgument('subscription'));
        $states = $this->states($id);
        $namespaces = \array_keys($states);

        $style->title("Resource providers: $name ($id)");
        $style->writeln(' <fg=gray>●</> registered   <info>✓</info> to register');

        $rows = \array_map(
            fn (string $namespace): string => $this->row($namespace, $states[$namespace] === 'Registering' ? 'registering' : ''),
            $namespaces
        );
        $locked = $this->registeredKeys(\array_values($states));
        $selected = $this->select($style, $rows, [], $locked);

        if ($selected === null) {
            return self::SUCCESS;
        }

        return $this->register($style, $id, $this->namespaces($namespaces, $selected, $locked));
    }

    /**
     * @param string[] $states
     * @return int[]
     */
    protected function registeredKeys(array $states): array
    {
        return \array_keys(\array_filter($states, fn (?string $state): bool => \in_array($state, self::REGISTERED, true)));
    }

    /**
     * @return string[] selected namespaces without locked ones
     */
    protected function namespaces(array $namespaces, array $selected, array $locked): array
    {
        return \array_values(\array_intersect_key($namespaces, \array_diff_key($selected, \array_flip($locked))));
    }

    protected function progressBar(int $done, int $total, int $width = 40): string
    {
        $filled = (int)\round($width * $done / $total);

        return \sprintf(
            ' <info>%s</info><fg=gray>%s</> %d/%d registered, %d left',
            \str_repeat('█', $filled),
            \str_repeat('░', $width - $filled),
            $done,
            $total,
            $total - $done
        );
    }

    /**
     * @return string[] [id, name]
     */
    protected function subscription(string $subscription): array
    {
        $account = \json_decode(
            \implode('', $this->az(
                'account show -o json --subscription ' . \escapeshellarg($subscription),
                "Reading subscription $subscription"
            )),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        return [$account['id'], $account['name']];
    }

    /**
     * @return array [namespace => registrationState], sorted by namespace
     */
    protected function states(string $subscriptionId, ?string $message = null): array
    {
        $states = [];
        $lines = $this->az(
            'provider list --query "[].[namespace, registrationState]" -o tsv --subscription '
            . \escapeshellarg($subscriptionId),
            $message ?? "Loading resource providers of $subscriptionId"
        );

        foreach ($lines as $line) {
            [$namespace, $state] = \explode("\t", $line);
            $states[$namespace] = $state;
        }

        \uksort($states, 'strcasecmp');

        return $states;
    }

    protected function row(string $namespace, string $note = ''): string
    {
        static $descriptions = null;
        $descriptions ??= \json_decode(\file_get_contents(__DIR__ . '/providers.json'), true, 512, \JSON_THROW_ON_ERROR);

        // " ❯ [x] " prefix takes 7 columns, longer rows wrap and break list redraw
        return \mb_strimwidth(
            \sprintf('%-45s %-12s %s', $namespace, $note, $descriptions[$namespace] ?? ''),
            0,
            (new Terminal())->getWidth() - 8,
            '…'
        );
    }

    /**
     * Last row is exit, enter on it ends command without changes.
     *
     * @return null|array selected keys as [key => true], null when exit chosen
     */
    protected function select(
        Style $style,
        array $rows,
        array $selected,
        array $locked,
        string $selectedChar = '<info>✓</info>'
    ): ?array {
        $style->writeln(' <fg=gray>space: toggle, ↑/↓: move, enter: confirm (on Exit: quit)</>');
        $style->newLine();

        $multiSelect = (new MultiSelect($style))
            ->toggleShowInfo(false)
            ->setSelectedChar($selectedChar);
        $selected = $multiSelect->renderMultiSelect([...$rows, '<fg=red>✗ Exit</>'], $selected, $locked);

        if ($multiSelect->getCursor() === \count($rows)) {
            $style->newLine();
            $style->writeln(' Exited.');

            return null;
        }

        return $selected;
    }

    protected function register(Style $style, string $subscriptionId, array $namespaces): int
    {
        $style->newLine();

        if (!$namespaces) {
            $style->writeln(' Nothing to register.');

            return self::SUCCESS;
        }

        foreach ($namespaces as $namespace) {
            $this->az(
                'provider register --namespace ' . \escapeshellarg($namespace)
                . ' --subscription ' . \escapeshellarg($subscriptionId),
                "Registering $namespace"
            );
            $style->writeln(" Registering <info>$namespace</info>");
        }

        $style->newLine();
        $style->writeln(' Waiting for registration, enter stops watching (registration continues in Azure):');
        $this->waitForRegistration($style, $subscriptionId, $namespaces);

        return self::SUCCESS;
    }

    /**
     * Poll states every 10s, redraw list with spinner every 100ms (only on decorated output).
     */
    protected function waitForRegistration(Style $style, string $subscriptionId, array $namespaces): void
    {
        $live = $style->isDecorated();
        // unbuffered, so stream_select sees every key
        \stream_set_read_buffer(\STDIN, 0);

        for ($frame = 0; ; $frame++) {
            if ($frame % 100 === 0) {
                $states = $this->states($subscriptionId, 'Checking registration state');
                $done = !\array_diff($namespaces, \array_keys($states, 'Registered', true));
            }

            if ($live && $frame > 0) {
                // provider rows + progress bar
                $style->write("\033[" . (\count($namespaces) + 1) . 'A');
            }

            if ($live || $done) {
                foreach ($namespaces as $namespace) {
                    $state = $states[$namespace] ?? '';
                    $icon = $state === 'Registered'
                        ? '<info>✓</info>'
                        : '<fg=blue>' . self::SPINNER[$frame % \count(self::SPINNER)] . '</>';

                    $style->writeln(\sprintf(' %s %-45s %-12s', $icon, $namespace, $state));
                }

                $style->writeln($this->progressBar(\count(\array_intersect($namespaces, \array_keys($states, 'Registered', true))), \count($namespaces)));
            }

            if ($done) {
                return;
            }

            // waits 100ms for enter without switching stdin to non-blocking (it's shared with shell)
            $read = [\STDIN];
            $none = [];

            if (\stream_select($read, $none, $none, 0, 100000)) {
                $char = \fgetc(\STDIN);

                if ($char === "\n") {
                    $style->writeln(' Stopped watching, registration continues in Azure.');

                    return;
                }

                if ($char === false) {
                    // closed stdin (pipe) is always readable
                    \usleep(100000);
                }
            }
        }
    }

    /**
     * Shows spinner with message on stderr (only terminal) while az is running.
     *
     * @return string[] output lines (stderr goes straight to terminal)
     */
    protected function az(string $arguments, string $message): array
    {
        $process = \proc_open("az $arguments", [1 => ['pipe', 'w']], $pipes);
        \stream_set_blocking($pipes[1], false);
        $spinner = \stream_isatty(\STDERR);
        $output = '';

        // exitcode is valid only in first status with running = false
        for ($frame = 0; ($status = \proc_get_status($process))['running']; $frame++) {
            $output .= \stream_get_contents($pipes[1]);

            if ($spinner) {
                \fwrite(\STDERR, "\r " . self::SPINNER[$frame % \count(self::SPINNER)] . " $message");
            }

            \usleep(100000);
        }

        $output .= \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        \proc_close($process);

        if ($spinner) {
            \fwrite(\STDERR, "\r\033[K");
        }

        if ($status['exitcode'] !== 0) {
            throw new \RuntimeException("az $arguments failed with code {$status['exitcode']}");
        }

        return $output === '' ? [] : \explode("\n", \rtrim($output, "\n"));
    }
}
