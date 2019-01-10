<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletRequest;
use Shopware\Storefront\Pagelet\Search\SearchPageletRequest;

class SearchPageRequest extends Struct
{
    /**
     * @var SearchPageletRequest
     */
    protected $searchRequest;

    /**
     * @var ContentHeaderPageletRequest
     */
    protected $headerRequest;

    public function __construct()
    {
        $this->searchRequest = new SearchPageletRequest();
        $this->headerRequest = new ContentHeaderPageletRequest();
    }

    /**
     * @return SearchPageletRequest
     */
    public function getSearchRequest(): SearchPageletRequest
    {
        return $this->searchRequest;
    }

    public function getHeaderRequest(): ContentHeaderPageletRequest
    {
        return $this->headerRequest;
    }
}
