<?php

declare(strict_types=1);

/*
 * This file is part of the "routing_mcp" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3RoutingMcp\Command;

use KonradMichalik\Typo3RoutingMcp\Mcp\{ExposurePolicy, ToolCatalog};
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_map;
use function count;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * McpToolsCommand.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[AsCommand(name: 'routing:mcp:tools', description: 'List routes annotated with #[McpTool] and whether they are exposed')]
final class McpToolsCommand extends Command
{
    public function __construct(
        private readonly ExposurePolicy $exposurePolicy,
        private readonly ToolCatalog $toolCatalog,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON (machine-readable)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = $this->collectRows();

        if (true === $input->getOption('json')) {
            $output->writeln(json_encode($rows, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        if ([] === $rows) {
            $io->warning('No routes carry #[McpTool].');

            return Command::SUCCESS;
        }

        $io->title('MCP Tools');
        $io->table(
            ['Route', 'Tool name', 'Description', 'Read-only', 'Arguments', 'Status'],
            array_map(static fn (array $row): array => [
                $row['route'],
                $row['name'],
                $row['description'] ?? '-',
                $row['readOnly'] ? 'yes' : 'no',
                $row['arguments'] ?? '-',
                $row['status'],
            ], $rows),
        );

        return Command::SUCCESS;
    }

    /**
     * @return list<array{route: string, name: string, description: string|null, readOnly: bool, arguments: int|null, status: string}>
     */
    private function collectRows(): array
    {
        $argumentCounts = [];
        foreach ($this->toolCatalog->list() as $definition) {
            $argumentCounts[$definition->routeName] = count($definition->inputSchema['properties']);
        }

        $rows = [];
        foreach ($this->exposurePolicy->all() as $routeName => $entry) {
            $rows[] = [
                'route' => $routeName,
                'name' => $entry['name'],
                'description' => $entry['description'],
                'readOnly' => $entry['readOnly'],
                'arguments' => $argumentCounts[$routeName] ?? null,
                'status' => $this->exposurePolicy->isExposed($routeName)
                    ? 'Exposed'
                    : 'Excluded: '.($entry['excludedReason'] ?? 'environment mismatch'),
            ];
        }

        return $rows;
    }
}
