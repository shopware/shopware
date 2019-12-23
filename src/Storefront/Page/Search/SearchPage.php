<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\Page;

class SearchPage extends Page
{
    /**
     * @deprecated tag:v6.3.0 use self::listing instead
     *
     * @var StorefrontSearchResult
     */
    protected $searchResult;

    /**
     * @var string
     */
    protected $searchTerm;

    /**
     * @var ProductListingResult
     */
    protected $listing;

    /**
     * @deprecated tag:v6.3.0 use self::getListing instead
     */
    public function getSearchResult(): StorefrontSearchResult
    {
        return $this->searchResult;
    }

    /**
     * @deprecated tag:v6.3.0 use self::setListing instead
     */
    public function setSearchResult(StorefrontSearchResult $searchResult): void
    {
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

    public function getListing(): ProductListingResult
    {
        return $this->listing;
    }

    public function setListing(ProductListingResult $listing): void
    {
        $this->listing = $listing;
    }
}
