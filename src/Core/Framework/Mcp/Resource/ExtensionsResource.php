<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Lists optional MCP capability plugins that extend the core platform.
 * AI clients should read this resource when a requested tool is not available.
 *
 * @internal
 */
#[McpResource(uri: 'shopware://extensions', name: 'shopware-extensions', description: 'Optional MCP capability plugins. Read this when a requested tool is not available to find the right extension and its install command.')]
#[Package('framework')]
class ExtensionsResource
{
    public function __construct(
        private readonly Connection $connection,
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $extensions = $this->getKnownExtensions();
        $statuses = $this->resolveStatuses($extensions);

        $result = [];
        foreach ($extensions as $extension) {
            $status = $statuses[$extension['name']];

            $entry = [
                'name' => $extension['name'],
                'type' => $extension['type'],
                'tool_prefix' => $extension['tool_prefix'],
                'description' => $extension['description'],
                'status' => $status,
                'install_command' => $this->resolveInstallCommand($extension, $status),
            ];

            if ($extension['documentation_url'] !== null) {
                $entry['documentation_url'] = $extension['documentation_url'];
            }

            $result[] = $entry;
        }

        return [
            'uri' => 'shopware://extensions',
            'mimeType' => 'application/json',
            'text' => json_encode($result, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT),
        ];
    }

    /**
     * Returns the list of known optional MCP extensions.
     *
     * TODO: Replace with dynamic data from SBP (Shopware Plugin Store) API
     *       so extensions are discovered automatically rather than hardcoded here.
     *
     * @return list<array{name: string, type: 'plugin'|'bundle'|'app', tool_prefix: string, description: string, install_command: string, documentation_url: string|null}>
     */
    protected function getKnownExtensions(): array
    {
        return [
            [
                'name' => 'SwagMcpMerchantAssistant',
                'type' => 'plugin',
                'tool_prefix' => 'merchant-',
                'description' => 'Merchant workflow tools: order management, customer lookup, product creation, revenue and bestseller reports, storefront search, and cart/checkout.',
                'install_command' => 'bin/console plugin:install --activate SwagMcpMerchantAssistant',
                'documentation_url' => 'https://github.com/shopware/SwagMcpMerchantAssistant',
            ],
        ];
    }

    /**
     * @param array{name: string, type: string, install_command: string} $extension
     */
    private function resolveInstallCommand(array $extension, string $status): ?string
    {
        return match ($status) {
            'active' => null,
            'installed' => match ($extension['type']) {
                'plugin' => \sprintf('bin/console plugin:activate %s', $extension['name']),
                default => null,
            },
            default => $extension['install_command'],
        };
    }

    /**
     * @param list<array{name: string, type: 'plugin'|'bundle'|'app', tool_prefix: string, description: string, install_command: string, documentation_url: string|null}> $extensions
     *
     * @return array<string, 'not_installed'|'installed'|'active'>
     */
    private function resolveStatuses(array $extensions): array
    {
        $statuses = [];

        $pluginNames = $this->filterNamesByType($extensions, 'plugin');
        $appNames = $this->filterNamesByType($extensions, 'app');

        $pluginRows = $pluginNames !== [] ? $this->connection->fetchAllKeyValue(
            'SELECT `name`, `active` FROM `plugin` WHERE `name` IN (:names)',
            ['names' => $pluginNames],
            ['names' => ArrayParameterType::STRING],
        ) : [];

        $appRows = $appNames !== [] ? $this->connection->fetchAllKeyValue(
            'SELECT `name`, `active` FROM `app` WHERE `name` IN (:names)',
            ['names' => $appNames],
            ['names' => ArrayParameterType::STRING],
        ) : [];

        foreach ($extensions as $extension) {
            $rows = match ($extension['type']) {
                'plugin' => $pluginRows,
                'app' => $appRows,
                'bundle' => [],
            };

            $statuses[$extension['name']] = match ($extension['type']) {
                'plugin', 'app' => isset($rows[$extension['name']])
                    ? ((bool) $rows[$extension['name']] ? 'active' : 'installed')
                    : 'not_installed',
                'bundle' => isset($this->kernel->getBundles()[$extension['name']]) ? 'active' : 'not_installed',
            };
        }

        return $statuses;
    }

    /**
     * @param list<array{name: string, type: 'plugin'|'bundle'|'app', tool_prefix: string, description: string, install_command: string, documentation_url: string|null}> $extensions
     *
     * @return list<string>
     */
    private function filterNamesByType(array $extensions, string $type): array
    {
        return array_values(array_column(
            array_filter($extensions, static fn (array $e) => $e['type'] === $type),
            'name',
        ));
    }
}
