<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Builds a minimal toolset taxonomy from tool names until curated toolset metadata exists.
 */
#[Package('framework')]
class McpToolsetRegistry
{
    final public const DEFAULT_TOOLSET = 'default';

    final public const LIST_TOOLSETS_TOOL = 'shopware-toolsets-list';

    final public const ENABLE_TOOLSET_TOOL = 'shopware-toolset-enable';

    private const DEFAULT_TOOLS = [
        self::LIST_TOOLSETS_TOOL,
        self::ENABLE_TOOLSET_TOOL,
    ];

    /**
     * @var array<string, array{title: string, description: string, prefixes: list<string>}>
     */
    private const CORE_TOOLSETS = [
        'shopware-entity' => [
            'title' => 'Entity tools',
            'description' => 'DAL entity schema, read, search, aggregation, upsert, and delete tools.',
            'prefixes' => ['shopware-entity-'],
        ],
        'shopware-system-config' => [
            'title' => 'System configuration tools',
            'description' => 'Read and write Shopware system configuration.',
            'prefixes' => ['shopware-system-config-'],
        ],
        'shopware-order' => [
            'title' => 'Order tools',
            'description' => 'Order state and order workflow tools.',
            'prefixes' => ['shopware-order-'],
        ],
        'shopware-media' => [
            'title' => 'Media tools',
            'description' => 'Media upload and media library tools.',
            'prefixes' => ['shopware-media-'],
        ],
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly McpCapabilityCatalog $catalog,
    ) {
    }

    /**
     * @return list<array{name: string, title: string, description: string, tools: list<string>, enabledByDefault: bool}>
     */
    public function toolsets(): array
    {
        $toolNames = array_values(array_map(
            static fn (array $tool): string => $tool['name'],
            $this->catalog->enrichedTools(),
        ));

        $assigned = [];
        $toolsets = [[
            'name' => self::DEFAULT_TOOLSET,
            'title' => 'Default tools',
            'description' => 'Meta-tools that are advertised before enabling additional session toolsets.',
            'tools' => $this->existingTools(self::DEFAULT_TOOLS, $toolNames),
            'enabledByDefault' => true,
        ]];

        foreach (self::CORE_TOOLSETS as $name => $definition) {
            $tools = $this->toolsForPrefixes($toolNames, $definition['prefixes']);
            if ($tools === []) {
                continue;
            }

            array_push($assigned, ...$tools);
            $toolsets[] = [
                'name' => $name,
                'title' => $definition['title'],
                'description' => $definition['description'],
                'tools' => $tools,
                'enabledByDefault' => false,
            ];
        }

        $fallback = [];
        foreach ($toolNames as $toolName) {
            if (\in_array($toolName, self::DEFAULT_TOOLS, true) || \in_array($toolName, $assigned, true)) {
                continue;
            }

            $fallback[$this->fallbackToolsetName($toolName)][] = $toolName;
        }

        ksort($fallback);
        foreach ($fallback as $name => $tools) {
            sort($tools);
            $toolsets[] = [
                'name' => $name,
                'title' => $this->humanizeToolsetName($name),
                'description' => \sprintf('Tools inferred from the "%s-" name prefix.', $name),
                'tools' => $tools,
                'enabledByDefault' => false,
            ];
        }

        return $toolsets;
    }

    /**
     * @return array{name: string, title: string, description: string, tools: list<string>, enabledByDefault: bool}|null
     */
    public function find(string $name): ?array
    {
        foreach ($this->toolsets() as $toolset) {
            if ($toolset['name'] === $name) {
                return $toolset;
            }
        }

        return null;
    }

    /**
     * @param list<string> $enabledToolsets
     *
     * @return list<string>
     */
    public function advertisedTools(array $enabledToolsets): array
    {
        $tools = [];

        foreach ($this->toolsets() as $toolset) {
            if (!$toolset['enabledByDefault'] && !\in_array($toolset['name'], $enabledToolsets, true)) {
                continue;
            }

            array_push($tools, ...$toolset['tools']);
        }

        $tools = array_values(array_unique($tools));
        sort($tools);

        return $tools;
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     *
     * @return list<string>
     */
    private function existingTools(array $expected, array $actual): array
    {
        return array_values(array_intersect($expected, $actual));
    }

    /**
     * @param list<string> $toolNames
     * @param list<string> $prefixes
     *
     * @return list<string>
     */
    private function toolsForPrefixes(array $toolNames, array $prefixes): array
    {
        $tools = [];

        foreach ($toolNames as $toolName) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($toolName, $prefix)) {
                    $tools[] = $toolName;
                    break;
                }
            }
        }

        sort($tools);

        return $tools;
    }

    private function fallbackToolsetName(string $toolName): string
    {
        $parts = explode('-', $toolName);

        if (\count($parts) < 2) {
            return $toolName;
        }

        return $parts[0] . '-' . $parts[1];
    }

    private function humanizeToolsetName(string $name): string
    {
        return ucfirst(str_replace('-', ' ', $name)) . ' tools';
    }
}
