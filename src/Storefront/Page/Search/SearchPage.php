<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Storefront\Framework\Page\PageWithHeader;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;

class SearchPage extends PageWithHeader
{
    /**
     * @var StorefrontSearchResult
     */
    protected $listing;

    /**
     * @var string
     */
    protected $searchTerm;

    public function getListing(): StorefrontSearchResult
    {
        return $this->listing;
    }

    public function setListing(StorefrontSearchResult $listing): void
    {
        $this->listing = $listing;
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
