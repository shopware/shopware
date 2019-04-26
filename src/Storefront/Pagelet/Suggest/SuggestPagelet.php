<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Suggest;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;

class SuggestPagelet extends Struct
{
    /**
     * @var string
     */
    protected $searchTerm;

    /**
     * @var EntitySearchResult
     */
    protected $searchResult;

    public function __construct(EntitySearchResult $searchResult, string $searchTerm)
    {
        $this->searchTerm = $searchTerm;
        $this->searchResult = $searchResult;
    }

    public function getSearchResult(): EntitySearchResult
    {
        return $this->searchResult;
    }

    public function setSearchResult(EntitySearchResult $searchResult): void
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
