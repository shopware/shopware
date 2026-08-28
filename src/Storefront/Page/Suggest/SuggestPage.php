<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Suggest;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Page;

#[Package('discovery')]
class SuggestPage extends Page
{
    protected string $searchTerm;

    /**
     * @var EntitySearchResult<ProductCollection>
     */
    protected EntitySearchResult $searchResult;

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: ProductListingResult::class, description: 'Narrows from EntitySearchResult to ProductListingResult, which will no longer extend EntitySearchResult.')]
    public function getSearchResult(): EntitySearchResult
    {
        return $this->searchResult;
    }

    /**
     * @param EntitySearchResult<ProductCollection> $searchResult
     */
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'searchResult', newType: ProductListingResult::class)]
    public function setSearchResult(EntitySearchResult $searchResult): void
    {
        if (!$searchResult instanceof ProductListingResult) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Passing a plain "EntitySearchResult" to "SuggestPage::setSearchResult()" is deprecated. Pass a "ProductListingResult" instead.');
        }

        $this->searchResult = $searchResult;
    }

    public function getSearchTerm(): string
    {
        return $this->searchTerm;
    }

    public function setSearchTerm(string $searchTerm): void
    {
        $this->searchTerm = $searchTerm;
    }
}
