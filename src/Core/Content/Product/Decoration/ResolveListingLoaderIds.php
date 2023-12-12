<?php

namespace Shopware\Core\Content\Product\Decoration;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Decoration\Decoration;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ResolveListingLoaderIds extends Decoration
{
    public static function name(): string
    {
        return 'listing-loader.resolve-listing-ids';
    }

    public function result(): ?IdSearchResult
    {
        return $this->result;
    }

    public function __construct(
        public Criteria $criteria,
        public SalesChannelContext $context
    ) {}
}
