<?php declare(strict_types=1);

namespace Shopware\Search\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Search\Struct\SearchKeywordBasicStruct;

class SearchKeywordBasicCollection extends EntityCollection
{
    /**
     * @var SearchKeywordBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? SearchKeywordBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): SearchKeywordBasicStruct
    {
        return parent::current();
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (SearchKeywordBasicStruct $searchKeyword) {
            return $searchKeyword->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): SearchKeywordBasicCollection
    {
        return $this->filter(function (SearchKeywordBasicStruct $searchKeyword) use ($uuid) {
            return $searchKeyword->getShopUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return SearchKeywordBasicStruct::class;
    }
}
