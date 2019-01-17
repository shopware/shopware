<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletStruct;
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
     * @var ContentHeaderPageletStruct
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

    public function getHeader(): ContentHeaderPageletStruct
    {
        return $this->header;
    }

    public function setHeader(ContentHeaderPageletStruct $header): void
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
