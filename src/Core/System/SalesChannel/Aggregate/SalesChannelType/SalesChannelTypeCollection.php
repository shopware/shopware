<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class SalesChannelTypeCollection extends EntityCollection
{
    public function getSalesChannels(): SalesChannelCollection
    {
        return new SalesChannelCollection(
            $this->fmap(function (SalesChannelTypeEntity $salesChannel) {
                return $salesChannel->getSalesChannels();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelTypeEntity::class;
    }
}
