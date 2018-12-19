<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing\Page\AggregationView;

use Shopware\Core\Framework\Struct\Collection;

class AggregationViewCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return AggregationViewInterface::class;
    }
}
