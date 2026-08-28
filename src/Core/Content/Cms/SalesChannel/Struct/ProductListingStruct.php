<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel\Struct;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
class ProductListingStruct extends Struct
{
    /**
     * @var EntitySearchResult<ProductCollection>|null
     */
    protected ?EntitySearchResult $listing = null;

    /**
     * @return EntitySearchResult<ProductCollection>|null
     */
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: ProductListingResult::class, description: 'Narrows from ?EntitySearchResult to ?ProductListingResult, which will no longer extend EntitySearchResult.')]
    public function getListing(): ?EntitySearchResult
    {
        return $this->listing;
    }

    /**
     * @param EntitySearchResult<ProductCollection> $listing
     */
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'listing', newType: ProductListingResult::class)]
    public function setListing(EntitySearchResult $listing): void
    {
        if (!$listing instanceof ProductListingResult) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Passing a plain "EntitySearchResult" to "ProductListingStruct::setListing()" is deprecated. Pass a "ProductListingResult" instead.');
        }

        $this->listing = $listing;
    }

    public function getApiAlias(): string
    {
        return 'cms_product_listing';
    }
}
