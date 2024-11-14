<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Page;

#[Package('services-settings')]
class SearchPage extends Page
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $searchTerm;

    /**
     * @var ProductListingResult
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $listing;

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
