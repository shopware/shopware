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

    /**
     * @return list<string>
     */
    public function getTransactionalTypeIds(): array
    {
        $transactionalTypeIds = [];

        foreach ($this->capabilities as $typeCapabilities) {
            if ($typeCapabilities->isTransactional()) {
                $transactionalTypeIds[] = $typeCapabilities->getSalesChannelTypeId();
            }
        }

        return $transactionalTypeIds;
    }
}
