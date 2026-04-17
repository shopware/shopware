<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
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
        foreach ($this->providers as $provider) {
            if ($provider->getTechnicalName() === $technicalName) {
                return $provider;
            }
        }

        return null;
    }
}
