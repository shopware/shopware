<?php

namespace Shopware\Core\Content\Product\Extension;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ResolveListingExtension extends Extension
{
    public function __construct(
        public readonly Criteria $criteria,
        public readonly SalesChannelContext $context
    ) {
    }

    public static function name(): string
    {
        return 'listing-loader.resolve';
    }

    public function result(): EntitySearchResult
    {
        return $this->result;
    }
}
