<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Suggest;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Page;

#[Package('system-settings')]
class SuggestPage extends Page
{
    /**
     * @var string
     */
    protected $searchTerm;

    /**
     * @var EntitySearchResult
     */
    protected $searchResult;

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
