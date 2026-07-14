<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Exception as DBALException;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Feature\AppFeatureException;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[Package('framework')]
abstract class AbstractAppMcpLoader implements LoaderInterface
{
    public function __construct(
        protected readonly AppFeatureStorage $storage,
        protected readonly AppMcpCapabilityExecutor $executor,
        protected readonly LanguageLocaleCodeProvider $localeProvider,
        protected readonly LoggerInterface $logger,
    ) {
    }

    public function load(RegistryInterface $registry): void
    {
        try {
            $rows = $this->fetchRows();
        } catch (DBALException) {
            return;
        } catch (AppFeatureException) {
            // the MCP feature type is only registered when MCP_SERVER is enabled;
            // with the flag off there is nothing to load
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
            $this->logger->warning(\sprintf('App %s name uses reserved "shopware-" prefix, skipping', $type), [
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

    /**
     * The label/description locale to show. Matches the old loaders' hardcoded system-language read.
     */
    protected function systemLocale(): string
    {
        return $this->localeProvider->getLocaleForLanguageId(Defaults::LANGUAGE_SYSTEM);
    }
}
