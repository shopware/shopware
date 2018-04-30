<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Storefront\Page\Listing\ListingPageRequest;

class SearchPageRequest extends ListingPageRequest
{
    /**
     * @var string
     */
    protected $searchTerm;

    public function getSearchTerm(): string
    {
        return $this->searchTerm;
    }

    public function setSearchTerm(string $searchTerm): void
    {
        $this->searchTerm = $searchTerm;
    }
}
