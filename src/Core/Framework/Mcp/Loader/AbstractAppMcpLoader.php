<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[Package('framework')]
abstract class AbstractAppMcpLoader implements LoaderInterface
{
    public function __construct(
        protected readonly Connection $connection,
        protected readonly AppMcpCapabilityExecutor $executor,
    ) {
    }

    public function load(RegistryInterface $registry): void
    {
        try {
            $rows = $this->fetchRows();
        } catch (DBALException) {
            return;
        }

        foreach ($rows as $row) {
            $this->registerCapability($registry, $row);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    abstract protected function fetchRows(): array;

    /**
     * @param array<string, mixed> $row
     */
    abstract protected function registerCapability(RegistryInterface $registry, array $row): void;

    protected function capabilityName(string $appName, string $name): string
    {
        return $appName . '-' . $name;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function resolveDescription(array $row, string $fallback): string
    {
        return (string) ($row['label'] ?? $row['description'] ?? $fallback);
    }
}
