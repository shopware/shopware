<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed and is going to be part of SwagAgenticCommerce
 */
#[Package('discovery')]
readonly class AgenticCommerceProductExportProviderRegistry
{
    /**
     * @internal
     *
     * @param iterable<AbstractAgenticCommerceProductExportProvider> $providers
     */
    public function __construct(private iterable $providers)
    {
    }

    public function getByTechnicalName(string $technicalName): ?AbstractAgenticCommerceProductExportProvider
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'Will be part of SwagAgenticCommerce'));

        foreach ($this->providers as $provider) {
            if ($provider->getTechnicalName() === $technicalName) {
                return $provider;
            }
        }

        return null;
    }
}
