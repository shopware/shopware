<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Search;

use Shopware\Storefront\Pagelet\Listing\ListingPageletStruct;

class SearchPageletStruct extends ListingPageletStruct
{
    /**
     * @var string
     */
    protected $searchTerm;

    /**
     * @return string
     */
    public function getSearchTerm(): string
    {
        return $this->searchTerm;
    }

    /**
     * @param string $searchTerm
     */
    public function setSearchTerm(string $searchTerm): void
    {
        $this->searchTerm = $searchTerm;
    }
}
