<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(
    name: 'shopware-instance-info',
    description: 'Returns live runtime facts about this Shopware instance: exact version, PHP version, environment (dev/prod), every installed plugin with its version and active state, and the database migration status (executed vs total vs pending). Use this as the first call when connecting to a new instance to establish baseline facts before querying further. Returns {success, data: {shopware_version, php_version, environment, project_dir, plugins: [...], migrations: {executed, total, pending}}}.',
)]
#[Package('framework')]
class InstanceInfoTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(): string
    {
        $projectDir = $this->kernel->getProjectDir();

        return $this->success([
            'shopware_version' => $this->resolveShopwareVersion($projectDir),
            'php_version' => \PHP_VERSION,
            'environment' => $this->kernel->getEnvironment(),
            'project_dir' => $projectDir,
            'plugins' => $this->queryInstalledPlugins(),
            'migrations' => $this->queryMigrationState(),
            'tip' => 'Call shopware-console-command("debug:mcp") to list all registered MCP tools. Call shopware-console-command("plugin:list") for plugin details including authors.',
        ]);
    }

    private function resolveShopwareVersion(string $projectDir): string
    {
        // Prefer the compiled container parameter — most reliable source.
        $container = $this->kernel->getContainer();
        if ($container->hasParameter('shopware.version')) {
            return (string) $container->getParameter('shopware.version');
        }

        // Fallback: read from composer.lock (production template projects).
        $lockFile = $projectDir . '/composer.lock';
        if (file_exists($lockFile)) {
            $lock = json_decode((string) file_get_contents($lockFile), true, 32);
            foreach ($lock['packages'] ?? [] as $package) {
                if ($package['name'] === 'shopware/core') {
                    return (string) $package['version'];
                }
            }
        }

        // Last resort: read version from composer.json directly (monorepo source).
        $composerFile = $projectDir . '/composer.json';
        if (file_exists($composerFile)) {
            $composer = json_decode((string) file_get_contents($composerFile), true, 8);
            if (isset($composer['version'])) {
                return (string) $composer['version'];
            }
        }

        return 'unknown — run "about" command for details';
    }

    /**
     * @return list<array{name: string, version: string, active: bool, installed_at: string|null}>
     */
    private function queryInstalledPlugins(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT name, version, active, installed_at FROM plugin ORDER BY name ASC',
            );

            return array_map(static function (array $row): array {
                return [
                    'name' => (string) $row['name'],
                    'version' => (string) ($row['version'] ?? 'unknown'),
                    'active' => (bool) $row['active'],
                    'installed_at' => isset($row['installed_at']) ? (string) $row['installed_at'] : null,
                ];
            }, $rows);
        } catch (\Throwable) {
            // Plugin table does not yet exist — instance not yet initialized.
            return [];
        }
    }

    /**
     * @return array{executed: int, total: int, pending: int, note?: string}
     */
    private function queryMigrationState(): array
    {
        try {
            $executed = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM migration WHERE `update` IS NOT NULL',
            );
            $total = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM migration',
            );

            return [
                'executed' => $executed,
                'total' => $total,
                'pending' => $total - $executed,
            ];
        } catch (\Throwable) {
            return [
                'executed' => 0,
                'total' => 0,
                'pending' => 0,
                'note' => 'Migration table not found — run bin/console system:install first.',
            ];
        }
    }
}
