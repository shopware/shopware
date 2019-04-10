<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;

class ProductListingStruct extends Struct
{
    /**
     * @var EntitySearchResult|null
     */
    protected $searchResult;

    public function getSearchResult(): ?EntitySearchResult
    {
        return $this->searchResult;
    }

    public function setSearchResult(EntitySearchResult $searchResult): void
    {
        $this->searchResult = $searchResult;
    }
}
