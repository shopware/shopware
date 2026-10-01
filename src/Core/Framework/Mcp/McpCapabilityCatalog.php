<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\Tool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;

/**
 * @experimental stableVersion:v6.8.0
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
     * @param array<string, string> $toolGroups tool-name => group
     *
     * $registry is nullable via nullOnInvalid(): null when the MCP bundle is absent.
     * Once MCP is stable (v6.8.0) remove the nullable type and the null guards
     * in all public methods.
     */
    public function __construct(
        private readonly ?RegistryInterface $registry,
        private readonly AppMcpPrivilegeProvider $privilegeProvider,
        private readonly array $toolDependencies = [],
        private readonly array $toolPrivileges = [],
        private readonly array $toolGroups = [],
    ) {
    }

    /**
     * Returns enriched tool data sorted by name, optionally filtered to the given allowlist.
     *
     * @param list<string>|null $allowlist null = all tools
     *
     * @return list<array{name: string, title: ?string, description: ?string, group: string, dependencies: list<string>, requiredPrivileges: array{static: list<string>, entityParam: ?string, operations: list<string>}|null}>
     */
    public function enrichedTools(?array $allowlist = null): array
    {
        if ($this->registry === null) {
            return [];
        }

        $appToolPrivileges = $this->privilegeProvider->getAppToolPrivileges();
        $appToolGroups = $this->privilegeProvider->getAppToolGroups();
        $toolGroups = $this->resolveToolGroups($appToolGroups);

        $tools = [];

        foreach ($this->registry->getTools()->references as $tool) {
            \assert($tool instanceof Tool);

            if ($allowlist !== null && !\in_array($tool->name, $allowlist, true)) {
                continue;
            }

            $tools[] = $this->buildToolEntry($tool->name, $tool->title, $tool->description, $appToolPrivileges, $toolGroups);
        }

        usort($tools, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $tools;
    }

    /**
     * Returns enriched data for a single tool, or null when not found.
     *
     * @return array{name: string, title: ?string, description: ?string, group: string, dependencies: list<string>, requiredPrivileges: array{static: list<string>, entityParam: ?string, operations: list<string>}|null}|null
     */
    public function findTool(string $name): ?array
    {
        if ($this->registry === null) {
            return null;
        }

        $appToolPrivileges = $this->privilegeProvider->getAppToolPrivileges();
        $appToolGroups = $this->privilegeProvider->getAppToolGroups();
        $toolGroups = $this->resolveToolGroups($appToolGroups);

        foreach ($this->registry->getTools()->references as $tool) {
            if (!$tool instanceof Tool || $tool->name !== $name) {
                continue;
            }

            return $this->buildToolEntry($tool->name, $tool->title, $tool->description, $appToolPrivileges, $toolGroups);
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
            \assert($resource instanceof ResourceDefinition);

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
            \assert($prompt instanceof Prompt);

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
     * @param array<string, string> $toolGroups
     *
     * @return array{name: string, title: ?string, description: ?string, group: string, dependencies: list<string>, requiredPrivileges: array{static: list<string>, entityParam: ?string, operations: list<string>}|null}
     */
    private function buildToolEntry(
        string $name,
        ?string $title,
        ?string $description,
        array $appToolPrivileges,
        array $toolGroups,
    ): array {
        $privileges = $this->toolPrivileges[$name]
            ?? (isset($appToolPrivileges[$name])
                ? ['static' => $appToolPrivileges[$name], 'entityParam' => null, 'operations' => []]
                : null);

        return [
            'name' => $name,
            'title' => $title,
            'description' => $description,
            'group' => $toolGroups[$name],
            'dependencies' => $this->toolDependencies[$name] ?? [],
            'requiredPrivileges' => $privileges,
        ];
    }

    /**
     * Explicit #[McpToolGroup] values take precedence over runtime app groups. Each remaining
     * tool uses the longest hyphen-separated prefix it shares with another unconfigured tool.
     *
     * @param array<string, string> $appToolGroups
     *
     * @return array<string, string> tool-name => group
     */
    private function resolveToolGroups(array $appToolGroups): array
    {
        \assert($this->registry !== null);

        $resolvedGroups = array_merge($appToolGroups, $this->toolGroups);
        $unconfiguredToolNames = [];

        foreach ($this->registry->getTools()->references as $tool) {
            \assert($tool instanceof Tool);

            if (isset($resolvedGroups[$tool->name])) {
                continue;
            }

            $unconfiguredToolNames[] = $tool->name;
        }

        foreach ($unconfiguredToolNames as $toolName) {
            $group = explode('-', $toolName)[0] ?: 'other';

            foreach ($unconfiguredToolNames as $otherToolName) {
                if ($otherToolName === $toolName) {
                    continue;
                }

                $sharedPrefix = $this->longestCommonPrefix([$toolName, $otherToolName]);
                if (substr_count($sharedPrefix, '-') > substr_count($group, '-')) {
                    $group = $sharedPrefix;
                }
            }

            $resolvedGroups[$toolName] = $group;
        }

        return $resolvedGroups;
    }

    /**
     * @param list<string> $names
     */
    private function longestCommonPrefix(array $names): string
    {
        if (\count($names) < 2) {
            return '';
        }

        $commonSegments = explode('-', $names[0]);

        foreach (\array_slice($names, 1) as $name) {
            $segments = explode('-', $name);
            $sharedSegmentCount = 0;
            $maximumSharedSegmentCount = min(\count($commonSegments), \count($segments));

            while ($sharedSegmentCount < $maximumSharedSegmentCount) {
                if (strtolower($segments[$sharedSegmentCount]) !== strtolower($commonSegments[$sharedSegmentCount])) {
                    break;
                }

                ++$sharedSegmentCount;
            }

            $commonSegments = \array_slice($commonSegments, 0, $sharedSegmentCount);
        }

        return implode('-', $commonSegments);
    }
}
