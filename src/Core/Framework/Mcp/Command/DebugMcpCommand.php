<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Command;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Prompt;
use Mcp\Schema\Resource;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Mcp\Server\Builder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\McpCapabilityCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[AsCommand(name: 'debug:mcp', description: 'List registered MCP capabilities (tools, prompts, resources)')]
#[Package('framework')]
class DebugMcpCommand extends Command
{
    /**
     * @internal
     *
     * $builder and $registry are nullable via nullOnInvalid(): null when the MCP
     * bundle is absent. Once MCP_SERVER is stable (v6.8.0) remove the nullable
     * types and the null guards in execute().
     */
    public function __construct(
        private readonly ?Builder $builder,
        private readonly ?RegistryInterface $registry,
        private readonly McpAllowlistProvider $allowlistProvider,
        private readonly McpCapabilityCatalog $catalog,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Show full details for a specific capability by name or URI');
        $this->addOption('integration', null, InputOption::VALUE_REQUIRED, 'Filter to tools allowed for this integration access key (SWIA...)');
        $this->addOption('tools', null, InputOption::VALUE_NONE, 'Limit output to tools only');
        $this->addOption('prompts', null, InputOption::VALUE_NONE, 'Limit output to prompts only');
        $this->addOption('resources', null, InputOption::VALUE_NONE, 'Limit output to resources only');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!Feature::isActive('MCP_SERVER') || $this->builder === null || $this->registry === null) {
            $io->error('MCP bundle is not installed.');

            return self::FAILURE;
        }

        $this->builder->build();

        $accessKey = $input->getOption('integration');
        $toolsAllowlist = null;
        if (\is_string($accessKey) && $accessKey !== '') {
            $allowlist = $this->allowlistProvider->forAccessKey($accessKey);
            $toolsAllowlist = $allowlist['tools'];
            if ($toolsAllowlist === null) {
                $io->note(\sprintf('Integration "%s": no tool restriction (all tools allowed).', $accessKey));
            } else {
                $io->note(\sprintf('Integration "%s": %d tool(s) allowed.', $accessKey, \count($toolsAllowlist)));
            }
        }

        $name = $input->getArgument('name');
        if ($name !== null) {
            return $this->renderDetail($io, $name);
        }

        $filterTools = (bool) $input->getOption('tools');
        $filterPrompts = (bool) $input->getOption('prompts');
        $filterResources = (bool) $input->getOption('resources');
        $noFilter = !$filterTools && !$filterPrompts && !$filterResources;

        if ($filterTools || $noFilter) {
            $this->renderTools($io, $toolsAllowlist);
        }
        if ($filterPrompts || $noFilter) {
            $this->renderPrompts($io);
        }
        if ($filterResources || $noFilter) {
            $this->renderResources($io);
            $this->renderResourceTemplates($io);
        }

        $io->writeln('Run <comment>debug:mcp <name></comment> to see full details for a specific capability.');
        $io->newLine();

        return self::SUCCESS;
    }

    private function renderDetail(SymfonyStyle $io, string $name): int
    {
        \assert($this->registry !== null);

        foreach ($this->registry->getTools()->references as $tool) {
            if (!$tool instanceof Tool) {
                continue; // @codeCoverageIgnore
            }
            if ($tool->name === $name) {
                $ref = $this->registry->getTool($name);
                $toolData = $this->catalog->findTool($name);
                $this->renderToolDetail($io, $tool, $ref->handler, $toolData);

                return self::SUCCESS;
            }
        }

        foreach ($this->registry->getPrompts()->references as $prompt) {
            if (!$prompt instanceof Prompt) {
                continue; // @codeCoverageIgnore
            }
            if ($prompt->name === $name) {
                $ref = $this->registry->getPrompt($name);
                $this->renderPromptDetail($io, $prompt, $ref->handler);

                return self::SUCCESS;
            }
        }

        foreach ($this->registry->getResources()->references as $resource) {
            if (!$resource instanceof Resource) {
                continue; // @codeCoverageIgnore
            }

            if (($resource->name ?? $resource->uri) === $name || $resource->uri === $name) {
                $ref = $this->registry->getResource($resource->uri, false);
                $this->renderResourceDetail($io, $resource, $ref->handler);

                return self::SUCCESS;
            }
        }

        $io->error(\sprintf('No capability found with name \'%s\'. Run \'debug:mcp\' to list all capabilities.', $name));

        return self::FAILURE;
    }

    /**
     * @param array{name: string, description: ?string, dependencies: list<string>, requiredPrivileges: array{static: list<string>, entityParam: ?string, operations: list<string>}|null}|null $toolData
     * @param \Closure|array{0: object|string, 1: string}|string $handler
     */
    private function renderToolDetail(SymfonyStyle $io, Tool $tool, \Closure|array|string $handler, ?array $toolData): void
    {
        $rows = [];
        $properties = $tool->inputSchema['properties'] ?? [];
        $required = \is_array($tool->inputSchema['required'] ?? null) ? $tool->inputSchema['required'] : [];

        if (\is_array($properties)) {
            foreach ($properties as $paramName => $def) {
                if (!\is_array($def)) {
                    continue; // @codeCoverageIgnore
                }
                $type = isset($def['type']) && \is_string($def['type']) ? $def['type'] : 'mixed';
                $req = \in_array($paramName, $required, true) ? 'required' : 'optional';
                $desc = isset($def['description']) && \is_string($def['description']) ? $def['description'] : '';
                if (isset($def['default'])) {
                    $desc .= ($desc !== '' ? '. ' : '') . 'Default: ' . \json_encode($def['default']);
                }
                $rows[] = [$paramName, $type, $req, $desc];
            }
        }

        $deps = $toolData['dependencies'] ?? [];
        $privilegeLabel = $this->formatPrivileges($toolData['requiredPrivileges'] ?? null);
        $meta = [['Type' => 'tool']];
        if ($tool->title !== null && $tool->title !== '') {
            $meta[] = ['Title' => $tool->title];
        }
        $meta[] = ['Source' => $this->describeHandler($handler)];
        if ($deps !== []) {
            $meta[] = ['Dependencies' => implode(', ', $deps)];
        }
        if ($privilegeLabel !== '') {
            $meta[] = ['Privileges' => $privilegeLabel];
        }

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
    private function renderPromptDetail(SymfonyStyle $io, Prompt $prompt, \Closure|array|string $handler): void
    {
        $rows = [];
        foreach ($prompt->arguments ?? [] as $arg) {
            $rows[] = [$arg->name, ($arg->required ?? false) ? 'required' : 'optional', $arg->description ?? ''];
        }

        $meta = [['Type' => 'prompt']];
        if ($prompt->title !== null && $prompt->title !== '') {
            $meta[] = ['Title' => $prompt->title];
        }
        $meta[] = ['Source' => $this->describeHandler($handler)];

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
    private function renderResourceDetail(SymfonyStyle $io, Resource $resource, \Closure|array|string $handler): void
    {
        $meta = [['Type' => 'resource'], ['URI' => $resource->uri], ['Source' => $this->describeHandler($handler)]];
        if ($resource->mimeType !== null) {
            $meta[] = ['MIME type' => $resource->mimeType];
        }

        $this->renderCapabilityDetail($io, $resource->name ?? $resource->uri, $meta, $resource->description);
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
        $io->title($title);
        $io->definitionList(...$meta);

        if ($description !== null && $description !== '') {
            $this->subSection($io, 'Description');
            $io->writeln($description);
        }

        if ($tableSection !== '' && $tableRows !== []) {
            $this->subSection($io, $tableSection);
            (new Table($io))->setHeaders($tableHeaders)->setRows($tableRows)->render();
        }

        $io->newLine();
    }

    private function subSection(SymfonyStyle $io, string $title): void
    {
        $io->newLine();
        $io->writeln(\sprintf('<comment>%s</>', $title));
        $io->writeln(\sprintf('<comment>%s</>', str_repeat('-', mb_strlen($title))));
    }

    /**
     * @param list<string>|null $allowlist
     */
    private function renderTools(SymfonyStyle $io, ?array $allowlist = null): void
    {
        \assert($this->registry !== null);

        $enrichedTools = $this->catalog->enrichedTools($allowlist);
        $total = $this->catalog->totalToolCount();

        $heading = $allowlist !== null
            ? \sprintf('Tools (%d/%d allowed)', \count($enrichedTools), $total)
            : \sprintf('Tools (%d)', $total);

        $io->section($heading);

        if ($enrichedTools === []) {
            $io->text('No tools registered.');

            return;
        }

        $rows = [];
        foreach ($enrichedTools as $tool) {
            $ref = $this->registry->getTool($tool['name']);
            $deps = $tool['dependencies'];
            $rows[] = [
                $tool['name'],
                $this->describeHandler($ref->handler),
                $deps !== [] ? implode(', ', $deps) : '',
                $this->formatPrivileges($tool['requiredPrivileges']),
            ];
        }

        (new Table($io))
            ->setHeaders(['Name', 'Source', 'Dependencies', 'Privileges'])
            ->setRows($rows)
            ->render();
        $io->newLine();
    }

    private function renderPrompts(SymfonyStyle $io): void
    {
        \assert($this->registry !== null);

        $page = $this->registry->getPrompts();
        $io->section(\sprintf('Prompts (%d)', $page->count()));

        if ($page->count() === 0) {
            $io->text('No prompts registered.');

            return;
        }

        $rows = [];
        foreach ($page->references as $prompt) {
            if (!$prompt instanceof Prompt) {
                continue; // @codeCoverageIgnore
            }
            $ref = $this->registry->getPrompt($prompt->name);
            $rows[] = [$prompt->name, $this->describeHandler($ref->handler)];
        }

        $this->renderTable($io, $rows);
        $io->newLine();
    }

    private function renderResources(SymfonyStyle $io): void
    {
        \assert($this->registry !== null);

        $page = $this->registry->getResources();
        $io->section(\sprintf('Resources (%d)', $page->count()));

        if ($page->count() === 0) {
            $io->text('No resources registered.');

            return;
        }

        $rows = [];
        foreach ($page->references as $resource) {
            if (!$resource instanceof Resource) {
                continue; // @codeCoverageIgnore
            }

            $ref = $this->registry->getResource($resource->uri, false);
            $rows[] = [$resource->name ?? $resource->uri, $this->describeHandler($ref->handler)];
        }

        $this->renderTable($io, $rows);
        $io->newLine();
    }

    private function renderResourceTemplates(SymfonyStyle $io): void
    {
        \assert($this->registry !== null);

        $page = $this->registry->getResourceTemplates();
        $io->section(\sprintf('Resource Templates (%d)', $page->count()));

        if ($page->count() === 0) {
            $io->text('No resource templates registered.');

            return;
        }

        $rows = [];
        foreach ($page->references as $template) {
            if (!$template instanceof ResourceTemplate) {
                continue; // @codeCoverageIgnore
            }
            $ref = $this->registry->getResourceTemplate($template->uriTemplate);
            $rows[] = [$template->name, $template->uriTemplate, $this->describeHandler($ref->handler)];
        }

        (new Table($io))
            ->setHeaders(['Name', 'URI Template', 'Source'])
            ->setRows($rows)
            ->render();
        $io->newLine();
    }

    /**
     * @param array<array<string>> $rows
     */
    private function renderTable(SymfonyStyle $io, array $rows): void
    {
        (new Table($io))
            ->setHeaders(['Name', 'Source'])
            ->setRows($rows)
            ->render();
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
