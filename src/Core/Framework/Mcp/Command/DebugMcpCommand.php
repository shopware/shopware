<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Command;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\Tool;
use Mcp\Server\Builder;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpDebugCommandCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\McpCapabilityCatalog;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @phpstan-type McpScope array{name: string, label: string, builder: Builder, registry: RegistryInterface, catalog: McpCapabilityCatalog}
 *
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[AsCommand(name: 'debug:mcp', description: 'List registered MCP capabilities (tools, prompts, resources)')]
class DebugMcpCommand extends Command
{
    /**
     * @internal
     *
     * @param array<string, list<string>> $unassigned kind => service ids the bundle assigned to no server
     *
     * The builder and registry arguments are nullable via nullOnInvalid(): null when the MCP
     * bundle is absent. Once MCP is stable (v6.8.0) remove the nullable
     * types and the null guards in resolveScopes().
     */
    public function __construct(
        private readonly ?Builder $builder,
        private readonly ?RegistryInterface $registry,
        private readonly McpAllowlistProvider $allowlistProvider,
        private readonly McpCapabilityCatalog $catalog,
        private readonly ?Builder $storeApiBuilder = null,
        private readonly ?RegistryInterface $storeApiRegistry = null,
        private readonly ?McpCapabilityCatalog $storeApiCatalog = null,
        private readonly array $unassigned = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Show full details for a specific capability by name or URI');
        $this->addOption('integration', null, InputOption::VALUE_REQUIRED, 'Filter to tools allowed for this integration access key (SWIA...). Applies to the admin scope only');
        // Accepted and a no-op: this command only lists tools. Kept because external callers invoke
        // "debug:mcp --tools --no-ansi" (the MCP eval suite parses that table in CI).
        $this->addOption('tools', null, InputOption::VALUE_NONE, 'No-op: this command only lists tools. Use --native for prompts and resources');
        $this->addOption(
            'scope',
            null,
            InputOption::VALUE_REQUIRED,
            \sprintf('Limit output to one MCP server: "%s" or "%s". Omit to inspect both', ApiRouteScope::ID, StoreApiRouteScope::ID),
            null,
            [ApiRouteScope::ID, StoreApiRouteScope::ID],
        );
        $this->addOption(
            'native',
            null,
            InputOption::VALUE_NONE,
            \sprintf(
                'Run the MCP bundle\'s own "%s" command instead: the configured servers and clients, and any capability left unassigned by the "registry" patterns',
                McpDebugCommandCompilerPass::NATIVE_COMMAND_NAME,
            ),
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $integration = $input->getOption('integration');
        $scopeOption = $input->getOption('scope');
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('native')) {
            return $this->runNativeCommand($input, $output, $io);
        }

        $requestedScopes = match ($scopeOption) {
            null => [ApiRouteScope::ID, StoreApiRouteScope::ID],
            ApiRouteScope::ID => [ApiRouteScope::ID],
            StoreApiRouteScope::ID => [StoreApiRouteScope::ID],
            default => [],
        };

        if ($requestedScopes === []) {
            $io->error(\sprintf(
                'Invalid scope "%s". Use "%s" or "%s", or omit --scope to inspect both.',
                $scopeOption,
                ApiRouteScope::ID,
                StoreApiRouteScope::ID,
            ));

            return self::INVALID;
        }

        $scopes = $this->resolveScopes($requestedScopes);

        if ($scopes === []) {
            $io->error('MCP bundle is not installed.');

            return self::FAILURE;
        }

        foreach ($scopes as $scope) {
            $scope['builder']->build();
        }

        $toolsAllowlist = null;
        if ($integration !== null && $integration !== '') {
            $allowlist = $this->allowlistProvider->forAccessKey($integration);
            $toolsAllowlist = $allowlist->tools;
            if ($toolsAllowlist === null) {
                $io->note(\sprintf('Integration "%s": no tool restriction (all tools allowed).', $integration));
            } else {
                $io->note(\sprintf('Integration "%s": %d tool(s) allowed.', $integration, \count($toolsAllowlist)));
            }

            if (isset($scopes[StoreApiRouteScope::ID])) {
                $io->note('Integration allowlists only apply to the admin scope. Store API capabilities are listed unfiltered.');
            }
        }

        if ($name !== null) {
            return $this->renderDetail($io, $name, $scopes);
        }

        foreach ($scopes as $scopeId => $scope) {
            $io->title($scope['label']);

            $this->renderTools($io, $scope, $scopeId === ApiRouteScope::ID ? $toolsAllowlist : null);
        }

        $this->renderUnassigned($io);

        $io->writeln('Run <comment>debug:mcp <name></comment> to see full details for a specific capability.');
        if (\count($scopes) > 1) {
            $io->writeln(\sprintf('Run <comment>debug:mcp --scope=%s</comment> to inspect a single MCP server.', StoreApiRouteScope::ID));
        }
        $io->writeln(\sprintf(
            'Run <comment>bin/console %s</comment> for prompts, resources, the configured servers and clients.',
            McpDebugCommandCompilerPass::NATIVE_COMMAND_NAME,
        ));
        $io->newLine();

        return self::SUCCESS;
    }

    /**
     * Capabilities the MCP bundle collected but that no server exposes, because no `registry`
     * pattern in packages/mcp.php claims them and no compiler pass assigned them.
     *
     * This is how a capability silently disappears now that discovery no longer scans directories,
     * so it is reported here rather than only by the bundle's own command: a developer whose tool is
     * missing should not have to know a second command exists to find out why.
     */
    private function renderUnassigned(SymfonyStyle $io): void
    {
        $unassigned = array_filter($this->unassigned);

        if ($unassigned === []) {
            return;
        }

        $lines = [];
        foreach ($unassigned as $kind => $serviceIds) {
            foreach ($serviceIds as $serviceId) {
                $lines[] = \sprintf('%s (%s)', $serviceId, $kind);
            }
        }

        $io->warning(array_merge(
            ['Registered with an MCP attribute but exposed by no server, so unreachable on both endpoints:'],
            $lines,
        ));
    }

    /**
     * Hands over to the MCP bundle's command, which McpDebugCommandCompilerPass renamed so both can
     * coexist. Only the options that command actually declares are forwarded.
     */
    private function runNativeCommand(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $application = $this->getApplication();

        if ($application === null || !$application->has(McpDebugCommandCompilerPass::NATIVE_COMMAND_NAME)) {
            $io->error(\sprintf('The "%s" command is not available. The MCP bundle provides it.', McpDebugCommandCompilerPass::NATIVE_COMMAND_NAME));

            return self::FAILURE;
        }

        $arguments = ['command' => McpDebugCommandCompilerPass::NATIVE_COMMAND_NAME];

        $name = $input->getArgument('name');
        if (\is_string($name) && $name !== '') {
            $arguments['name'] = $name;
        }

        return $application->find(McpDebugCommandCompilerPass::NATIVE_COMMAND_NAME)
            ->run(new ArrayInput($arguments), $output);
    }

    /**
     * Returns the requested scopes that are actually available, keyed by route scope ID and always
     * ordered admin before store-api. 'name' suffixes every section heading, 'label' titles the
     * scope block and names the owning server in the detail view.
     *
     * @param list<string> $requestedScopes
     *
     * @return array<string, McpScope>
     */
    private function resolveScopes(array $requestedScopes): array
    {
        $available = [];

        if ($this->builder !== null && $this->registry !== null) {
            $available[ApiRouteScope::ID] = [
                'name' => 'Admin API',
                'label' => 'Admin API (/api/_mcp)',
                'builder' => $this->builder,
                'registry' => $this->registry,
                'catalog' => $this->catalog,
            ];
        }

        if ($this->storeApiBuilder !== null && $this->storeApiRegistry !== null && $this->storeApiCatalog !== null) {
            $available[StoreApiRouteScope::ID] = [
                'name' => 'Store API',
                'label' => 'Store API (/store-api/_mcp)',
                'builder' => $this->storeApiBuilder,
                'registry' => $this->storeApiRegistry,
                'catalog' => $this->storeApiCatalog,
            ];
        }

        return array_intersect_key($available, array_flip($requestedScopes));
    }

    /**
     * @param array<string, McpScope> $scopes
     */
    private function renderDetail(SymfonyStyle $io, string $name, array $scopes): int
    {
        foreach ($scopes as $scope) {
            $registry = $scope['registry'];

            foreach ($registry->getTools()->references as $tool) {
                \assert($tool instanceof Tool);
                if ($tool->name === $name) {
                    $ref = $registry->getTool($name);
                    $toolData = $scope['catalog']->findTool($name);
                    $this->renderToolDetail($io, $tool, $ref->handler, $toolData, $scope['label']);

                    return self::SUCCESS;
                }
            }

            foreach ($registry->getPrompts()->references as $prompt) {
                \assert($prompt instanceof Prompt);
                if ($prompt->name === $name) {
                    $ref = $registry->getPrompt($name);
                    $this->renderPromptDetail($io, $prompt, $ref->handler, $scope['label']);

                    return self::SUCCESS;
                }
            }

            foreach ($registry->getResources()->references as $resource) {
                \assert($resource instanceof ResourceDefinition);

                if ($resource->name === $name || $resource->uri === $name) {
                    $ref = $registry->getResource($resource->uri, false);
                    $this->renderResourceDetail($io, $resource, $ref->handler, $scope['label']);

                    return self::SUCCESS;
                }
            }
        }

        $io->error(\sprintf('No capability found with name \'%s\'. Run \'debug:mcp\' to list all capabilities.', $name));

        return self::FAILURE;
    }

    /**
     * @param array{name: string, title: ?string, description: ?string, group: string, dependencies: list<string>, requiredPrivileges: array{static: list<string>, entityParam: ?string, operations: list<string>}|null}|null $toolData
     * @param \Closure|array{0: object|string, 1: string}|string $handler
     */
    private function renderToolDetail(SymfonyStyle $io, Tool $tool, \Closure|array|string $handler, ?array $toolData, string $scopeLabel): void
    {
        $rows = [];
        $properties = $tool->inputSchema['properties'] ?? [];
        $required = \is_array($tool->inputSchema['required']) ? $tool->inputSchema['required'] : [];

        if (\is_array($properties)) {
            foreach ($properties as $paramName => $def) {
                if (!\is_array($def)) {
                    continue;
                }
                $type = isset($def['type']) && \is_string($def['type']) ? $def['type'] : 'mixed';
                $req = \in_array($paramName, $required, true) ? 'required' : 'optional';
                $desc = isset($def['description']) && \is_string($def['description']) ? $def['description'] : '';
                if (isset($def['default'])) {
                    $desc .= ($desc !== '' ? '. ' : '') . 'Default: ' . Json::encode($def['default']);
                }
                $rows[] = [$paramName, $type, $req, $desc];
            }
        }

        $deps = $toolData['dependencies'] ?? [];
        $privilegeLabel = $this->formatPrivileges($toolData['requiredPrivileges'] ?? null);
        // Ordered so the block reads top-down: what it is, where it lives, what governs reaching it,
        // and only then how it is implemented. Title is always rendered so the shape does not shift
        // between capabilities that carry one and those that do not, and Handler comes last because
        // it is the longest value and the least common reason to open this view.
        $meta = [
            ['Title' => $tool->title !== null && $tool->title !== '' ? $tool->title : '-'],
            ['Type' => 'tool'],
            ['Scope' => $scopeLabel],
            ['Group' => $toolData['group'] ?? 'other'],
        ];
        if ($deps !== []) {
            $meta[] = ['Dependencies' => implode(', ', $deps)];
        }
        if ($privilegeLabel !== '') {
            $meta[] = ['Privileges' => $privilegeLabel];
        }
        $meta[] = ['Handler' => $this->describeHandler($handler)];

        $this->renderCapabilityDetail(
            $io,
            $tool->name,
            $meta,
            $tool->description,
            $rows !== [] ? 'Parameters' : '',
            ['Parameter', 'Type', '', 'Description'],
            $rows,
        );
    }

    /**
     * @param \Closure|array{0: object|string, 1: string}|string $handler
     */
    private function renderPromptDetail(SymfonyStyle $io, Prompt $prompt, \Closure|array|string $handler, string $scopeLabel): void
    {
        $rows = [];
        foreach ($prompt->arguments ?? [] as $arg) {
            $rows[] = [$arg->name, ($arg->required ?? false) ? 'required' : 'optional', $arg->description ?? ''];
        }

        $meta = [
            ['Title' => $prompt->title !== null && $prompt->title !== '' ? $prompt->title : '-'],
            ['Type' => 'prompt'],
            ['Scope' => $scopeLabel],
            ['Handler' => $this->describeHandler($handler)],
        ];

        $this->renderCapabilityDetail(
            $io,
            $prompt->name,
            $meta,
            $prompt->description,
            $rows !== [] ? 'Arguments' : '',
            ['Argument', '', 'Description'],
            $rows,
        );
    }

    /**
     * @param \Closure|array{0: object|string, 1: string}|string $handler
     */
    private function renderResourceDetail(SymfonyStyle $io, ResourceDefinition $resource, \Closure|array|string $handler, string $scopeLabel): void
    {
        $meta = [
            ['Title' => $resource->title !== null && $resource->title !== '' ? $resource->title : '-'],
            ['Type' => 'resource'],
            ['Scope' => $scopeLabel],
            ['URI' => $resource->uri],
        ];
        if ($resource->mimeType !== null) {
            $meta[] = ['MIME type' => $resource->mimeType];
        }
        $meta[] = ['Handler' => $this->describeHandler($handler)];

        $this->renderCapabilityDetail($io, $resource->name, $meta, $resource->description);
    }

    /**
     * @param list<array<string, string>> $meta
     * @param array<string> $tableHeaders
     * @param array<array<string>> $tableRows
     */
    private function renderCapabilityDetail(
        SymfonyStyle $io,
        string $title,
        array $meta,
        ?string $description,
        string $tableSection = '',
        array $tableHeaders = [],
        array $tableRows = [],
    ): void {
        // Every block closes with exactly one blank line, so the next heading never needs to open
        // with one. definitionList() and the title already behave that way.
        $io->title($title);
        $io->definitionList(...$meta);

        if ($description !== null && $description !== '') {
            $this->subSection($io, 'Description');
            $io->writeln($description);
            $io->newLine();
        }

        if ($tableSection !== '' && $tableRows !== []) {
            $this->subSection($io, $tableSection);
            (new Table($io))->setHeaders($tableHeaders)->setRows($tableRows)->render();
            $io->newLine();
        }
    }

    private function subSection(SymfonyStyle $io, string $title): void
    {
        $io->writeln(\sprintf('<comment>%s</>', $title));
        $io->writeln(\sprintf('<comment>%s</>', str_repeat('-', mb_strlen($title))));
    }

    /**
     * @param McpScope $scope
     * @param list<string>|null $allowlist
     */
    private function renderTools(SymfonyStyle $io, array $scope, ?array $allowlist = null): void
    {
        $registry = $scope['registry'];
        $enrichedTools = $scope['catalog']->enrichedTools($allowlist);
        $total = $scope['catalog']->totalToolCount();

        $heading = $allowlist !== null
            ? \sprintf('Tools (%d/%d allowed) [%s]', \count($enrichedTools), $total, $scope['name'])
            : \sprintf('Tools (%d) [%s]', $total, $scope['name']);

        $io->section($heading);

        if ($enrichedTools === []) {
            $io->text('No tools registered.');
            $io->newLine();

            return;
        }

        $rows = [];
        foreach ($enrichedTools as $tool) {
            $ref = $registry->getTool($tool['name']);
            $deps = $tool['dependencies'];
            $rows[] = [
                $tool['name'],
                $tool['group'],
                $this->describeHandler($ref->handler),
                $deps !== [] ? implode(', ', $deps) : '',
                $this->formatPrivileges($tool['requiredPrivileges']),
            ];
        }

        (new Table($io))
            ->setHeaders(['Name', 'Group', 'Handler', 'Dependencies', 'Privileges'])
            ->setRows($rows)
            ->render();
        $io->newLine();
    }

    /**
     * @param array{static: list<string>, entityParam: ?string, operations: list<string>}|null $privileges
     */
    private function formatPrivileges(?array $privileges): string
    {
        if ($privileges === null) {
            return '';
        }

        $parts = $privileges['static'];

        if ($privileges['entityParam'] !== null) {
            foreach ($privileges['operations'] as $operation) {
                $parts[] = '<' . $privileges['entityParam'] . '>:' . $operation;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * @param \Closure|array{0: object|string, 1: string}|string $handler
     */
    private function describeHandler(\Closure|array|string $handler): string
    {
        if ($handler instanceof \Closure) {
            return '(app-provided)';
        }

        if (\is_array($handler)) {
            $class = \is_object($handler[0]) ? $handler[0]::class : $handler[0];

            return $class . '::' . $handler[1];
        }

        return $handler;
    }
}
