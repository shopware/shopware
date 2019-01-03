<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Header\HeaderPageletRequestTrait;
use Shopware\Storefront\Pagelet\Search\SearchPageletRequest;

class SearchPageRequest extends Struct
{
    use HeaderPageletRequestTrait;

    /**
     * @var SearchPageletRequest
     */
    protected $searchRequest;

    /**
     * @return SearchPageletRequest
     */
    public function getSearchRequest(): SearchPageletRequest
    {
        return $this->searchRequest;
    }

    /**
     * @param SearchPageletRequest $searchRequest
     */
    public function setSearchRequest(SearchPageletRequest $searchRequest): void
    {
        $this->searchRequest = $searchRequest;
    }
}
