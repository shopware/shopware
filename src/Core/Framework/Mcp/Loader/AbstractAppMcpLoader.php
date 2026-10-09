<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Shopware\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @template T of McpToolConfig|McpPromptConfig|McpResourceConfig
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
        $features = $this->storage->forActiveApps($this->getConfigClass());

        $locale = $this->localeProvider->getLocaleForLanguageId(Defaults::LANGUAGE_SYSTEM);

        foreach ($features as $feature) {
            $this->registerCapability($registry, $feature, $locale);
        }
    }

    /**
     * @return class-string<T>
     */
    abstract protected function getConfigClass(): string;

    /**
     * @param AppFeature<T> $feature
     */
    abstract protected function registerCapability(RegistryInterface $registry, AppFeature $feature, string $locale): void;

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

    protected function resolveDescription(?string $description, ?string $label, string $fallback): string
    {
        return $description ?: ($label ?: $fallback);
    }
}
