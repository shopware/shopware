<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Search;

use Shopware\Storefront\Pagelet\Listing\ListingPageletRequest;

class SearchPageletRequest extends ListingPageletRequest
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
