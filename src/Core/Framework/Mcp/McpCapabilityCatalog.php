<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Prompt;
use Mcp\Schema\Resource;
use Mcp\Schema\Tool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Provides enriched capability data by combining registry tools with dependency
 * and privilege metadata. Used by the capabilities API and the debug CLI command.
 */
#[Package('framework')]
class McpCapabilityCatalog
{
    /**
     * @internal
     *
     * @param array<string, list<string>> $toolDependencies tool-name => [dep-name, ...]
     * @param array<string, array{static: list<string>, entityParam: ?string, operations: list<string>}> $toolPrivileges tool-name => privilege info
     *
     * $registry is nullable via nullOnInvalid(): null when the MCP bundle is absent.
     * Once MCP_SERVER is stable (v6.8.0) remove the nullable type and the null guards
     * in all public methods.
     */
    public function __construct(
        private readonly ?RegistryInterface $registry,
        private readonly AppMcpPrivilegeProvider $privilegeProvider,
        private readonly array $toolDependencies = [],
        private readonly array $toolPrivileges = [],
    ) {
    }

    /**
     * Returns enriched tool data sorted by name, optionally filtered to the given allowlist.
     *
     * @param list<string>|null $allowlist null = all tools
     *
     * @return list<array{name: string, title: ?string, description: ?string, dependencies: list<string>, requiredPrivileges: array{static: list<string>, entityParam: ?string, operations: list<string>}|null}>
     */
    public function enrichedTools(?array $allowlist = null): array
    {
        if ($this->registry === null) {
            return [];
        }

        $appToolPrivileges = $this->privilegeProvider->getAppToolPrivileges();

        $tools = [];

        foreach ($this->registry->getTools()->references as $tool) {
            if (!$tool instanceof Tool) {
                continue; // @codeCoverageIgnore
            }

            if ($allowlist !== null && !\in_array($tool->name, $allowlist, true)) {
                continue;
            }

            $tools[] = $this->buildToolEntry($tool->name, $tool->title, $tool->description, $appToolPrivileges);
        }

        usort($tools, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $tools;
    }

    /**
     * Returns enriched data for a single tool, or null when not found.
     *
     * @return array{name: string, title: ?string, description: ?string, dependencies: list<string>, requiredPrivileges: array{static: list<string>, entityParam: ?string, operations: list<string>}|null}|null
     */
    public function findTool(string $name): ?array
    {
        if ($this->registry === null) {
            return null;
        }

        $appToolPrivileges = $this->privilegeProvider->getAppToolPrivileges();

        foreach ($this->registry->getTools()->references as $tool) {
            if (!$tool instanceof Tool || $tool->name !== $name) {
                continue;
            }

            return $this->buildToolEntry($tool->name, $tool->title, $tool->description, $appToolPrivileges);
        }

        return null;
    }

    public function totalToolCount(): int
    {
        return $this->registry?->getTools()->count() ?? 0;
    }

    /**
     * Returns resource data sorted by URI, optionally filtered to the given allowlist.
     *
     * @param list<string>|null $allowlist null = all resources
     *
     * @return list<array{uri: string, name: string, description: ?string, mimeType: ?string}>
     */
    public function enrichedResources(?array $allowlist = null): array
    {
        if ($this->registry === null) {
            return [];
        }

        $resources = [];

        foreach ($this->registry->getResources()->references as $resource) {
            if (!$resource instanceof Resource) {
                continue; // @codeCoverageIgnore
            }

            if ($allowlist !== null && !\in_array($resource->uri, $allowlist, true)) {
                continue;
            }

            $resources[] = [
                'uri' => $resource->uri,
                'name' => $resource->name,
                'description' => $resource->description,
                'mimeType' => $resource->mimeType,
            ];
        }

        usort($resources, static fn (array $a, array $b): int => $a['uri'] <=> $b['uri']);

        return $resources;
    }

    /**
     * Returns prompt data sorted by name, optionally filtered to the given allowlist.
     *
     * @param list<string>|null $allowlist null = all prompts
     *
     * @return list<array{name: string, title: ?string, description: ?string}>
     */
    public function enrichedPrompts(?array $allowlist = null): array
    {
        if ($this->registry === null) {
            return [];
        }

        $prompts = [];

        foreach ($this->registry->getPrompts()->references as $prompt) {
            if (!$prompt instanceof Prompt) {
                continue; // @codeCoverageIgnore
            }

            if ($allowlist !== null && !\in_array($prompt->name, $allowlist, true)) {
                continue;
            }

            $prompts[] = [
                'name' => $prompt->name,
                'title' => $prompt->title,
                'description' => $prompt->description,
            ];
        }

        usort($prompts, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $prompts;
    }

    /**
     * @param array<string, list<string>> $appToolPrivileges
     *
     * @return array{name: string, title: ?string, description: ?string, dependencies: list<string>, requiredPrivileges: array{static: list<string>, entityParam: ?string, operations: list<string>}|null}
     */
    private function buildToolEntry(string $name, ?string $title, ?string $description, array $appToolPrivileges): array
    {
        $privileges = $this->toolPrivileges[$name]
            ?? (isset($appToolPrivileges[$name])
                ? ['static' => $appToolPrivileges[$name], 'entityParam' => null, 'operations' => []]
                : null);

        return [
            'name' => $name,
            'title' => $title,
            'description' => $description,
            'dependencies' => $this->toolDependencies[$name] ?? [],
            'requiredPrivileges' => $privileges,
        ];
    }
}
