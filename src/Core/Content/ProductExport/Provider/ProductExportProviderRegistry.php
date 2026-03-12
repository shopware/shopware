<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Provider;

class ProductExportProviderRegistry
{
    /**
     * @param iterable<AbstractProductExportProvider> $providers
     */
    public function __construct(private readonly iterable $providers)
    {
    }

    public function getBySalesChannelType(string $salesChannelTypeId): ?AbstractProductExportProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->supportsSalesChannelType($salesChannelTypeId)) {
                return $provider;
            }
        }

        return null;
    }
}
