<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Psr\Log\LoggerInterface;
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
        protected readonly ?LoggerInterface $logger = null,
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

    protected function isReservedName(string $capabilityName, string $appName, string $type): bool
    {
        if (str_starts_with($capabilityName, 'shopware-')) {
            $this->logger?->warning(\sprintf('App %s name uses reserved "shopware-" prefix, skipping', $type), [
                'capabilityName' => $capabilityName,
                'appName' => $appName,
            ]);

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function resolveDescription(array $row, string $fallback): string
    {
        $description = isset($row['description']) && $row['description'] !== '' ? (string) $row['description'] : null;
        $label = isset($row['label']) && $row['label'] !== '' ? (string) $row['label'] : null;

        return $description ?? $label ?? $fallback;
    }
}
