<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use Shopware\Storefront\Pagelet\Search\SearchPageletStruct;

class SearchPageStruct extends Struct
{
    /**
     * @var SearchPageletStruct
     */
    protected $listing;

    /**
     * @var SearchPageletStruct
     */
    protected $search;

    /**
     * @var HeaderPagelet
     */
    protected $header;

    public function __construct()
    {
        $this->listing = &$this->search;
    }

    /**
     * @return SearchPageletStruct
     */
    public function getSearch(): SearchPageletStruct
    {
        return $this->search;
    }

    /**
     * @param SearchPageletStruct $search
     */
    public function setSearch(SearchPageletStruct $search): void
    {
        $this->search = $search;
    }

    public function getHeader(): HeaderPagelet
    {
        return $this->header;
    }

    public function setHeader(HeaderPagelet $header): void
    {
        $this->header = $header;
    }

    /**
     * @return SearchPageletStruct
     */
    public function getListing(): SearchPageletStruct
    {
        return $this->listing;
    }
}
