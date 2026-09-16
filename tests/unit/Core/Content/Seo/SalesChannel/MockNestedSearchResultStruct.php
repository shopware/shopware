<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SalesChannel;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Mimics a CMS slot data struct holding a search result directly in its vars.
 *
 * @internal
 */
class MockNestedSearchResultStruct extends Struct
{
    /**
     * @param EntitySearchResult<ProductCollection> $listing
     */
    public function __construct(protected EntitySearchResult $listing)
    {
    }
}
