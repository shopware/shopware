<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\Page;

class SearchPage extends Page
{
    /**
     * @var StorefrontSearchResult
     */
    protected $searchResult;

    /**
     * @var string
     */
    protected $searchTerm;

    public function getSearchResult(): StorefrontSearchResult
    {
        return $this->searchResult;
    }

    public function setSearchResult(StorefrontSearchResult $searchResult): void
    {
        $this->searchResult = $searchResult;
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
