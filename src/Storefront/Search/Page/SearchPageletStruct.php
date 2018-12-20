<?php declare(strict_types=1);

namespace Shopware\Storefront\Search\Page;

use Shopware\Storefront\Listing\Page\ListingPageletStruct;

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
