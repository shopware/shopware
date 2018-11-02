<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class SalesChannelTypeCollection extends EntityCollection
{
    /**
     * @var SalesChannelTypeStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? SalesChannelTypeStruct
    {
        return parent::get($id);
    }

    public function current(): SalesChannelTypeStruct
    {
        return parent::current();
    }

    public function getSalesChannels(): SalesChannelCollection
    {
        return new SalesChannelCollection(
            $this->fmap(function (SalesChannelTypeStruct $salesChannel) {
                return $salesChannel->getSalesChannels();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelTypeStruct::class;
    }
}
