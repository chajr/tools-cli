<?php

declare(strict_types=1);

namespace ToolsCli\Tools\Azure;

use BlueConsole\Style;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Register in target subscription providers registered in source one. Only adds, never unregisters.
 */
class ProvidersCloneTool extends ProvidersTool
{
    protected function configure(): void
    {
        $this->setName('azure:providers-clone')
            ->setDescription('Register in target subscription resource providers registered in source (az login required).')
            ->addArgument('source', InputArgument::REQUIRED, 'Source subscription name or id')
            ->addArgument('target', InputArgument::REQUIRED, 'Target subscription name or id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new Style($input, $output, new FormatterHelper());
        [$sourceId, $sourceName] = $this->subscription($input->getArgument('source'));
        [$targetId, $targetName] = $this->subscription($input->getArgument('target'));
        $source = $this->states($sourceId);
        $target = $this->states($targetId);
        $namespaces = \array_keys($source + $target);
        \usort($namespaces, 'strcasecmp');

        $style->title("Resource providers: $sourceName ($sourceId) -> $targetName ($targetId)");
        $style->writeln(' <fg=gray>●</> registered in target   <info>+</info> to register in target');

        $inSource = $this->registeredKeys(\array_map(fn (string $ns): ?string => $source[$ns] ?? null, $namespaces));
        $locked = $this->registeredKeys(\array_map(fn (string $ns): ?string => $target[$ns] ?? null, $namespaces));
        $rows = \array_map(
            fn (int $key, string $namespace): string => $this->row(
                $namespace,
                match ([\in_array($key, $inSource, true), \in_array($key, $locked, true)]) {
                    [true, true] => 'both',
                    [true, false] => 'source',
                    [false, true] => 'target',
                    default => '',
                }
            ),
            \array_keys($namespaces),
            $namespaces
        );

        $selected = $this->select($style, $rows, \array_diff($inSource, $locked), $locked, '<info>+</info>');

        return $this->register($style, $targetId, $this->namespaces($namespaces, $selected, $locked));
    }
}
