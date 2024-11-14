<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Suggest;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Page;

#[Package('services-settings')]
class SuggestPage extends Page
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $searchTerm;

    /**
     * @var EntitySearchResult<ProductCollection>
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $searchResult;

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    public function getSearchResult(): EntitySearchResult
    {
        return $this->searchResult;
    }

    /**
     * @param EntitySearchResult<ProductCollection> $searchResult
     */
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
