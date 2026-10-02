<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Capability;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class SalesChannelTypeCapabilityRegistry
{
    /**
     * @param iterable<AbstractSalesChannelTypeCapabilities> $capabilities
     */
    public function __construct(private iterable $capabilities)
    {
    }

    public function isTransactional(string $salesChannelTypeId): bool
    {
        return \in_array($salesChannelTypeId, $this->getTransactionalTypeIds(), true);
    }

    /**
     * @return list<string>
     */
    public function getTransactionalTypeIds(): array
    {
        $transactionalTypeIds = [];

        foreach (DefaultSalesChannelType::cases() as $defaultType) {
            if ($defaultType->isTransactional()) {
                $transactionalTypeIds[] = $defaultType->value;
            }
        }

        foreach ($this->capabilities as $typeCapabilities) {
            if ($typeCapabilities->isTransactional()) {
                $transactionalTypeIds[] = $typeCapabilities->getSalesChannelTypeId();
            }
        }

        return array_values(array_unique($transactionalTypeIds));
    }
}
