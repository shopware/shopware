<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Listing\Struct\ListingSortingTranslationBasicStruct;

class ListingSortingTranslationBasicCollection extends EntityCollection
{
    /**
     * @var ListingSortingTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ListingSortingTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ListingSortingTranslationBasicStruct
    {
        return parent::current();
    }

    public function getListingSortingUuids(): array
    {
        return $this->fmap(function (ListingSortingTranslationBasicStruct $listingSortingTranslation) {
            return $listingSortingTranslation->getListingSortingUuid();
        });
    }

    public function filterByListingSortingUuid(string $uuid): ListingSortingTranslationBasicCollection
    {
        return $this->filter(function (ListingSortingTranslationBasicStruct $listingSortingTranslation) use ($uuid) {
            return $listingSortingTranslation->getListingSortingUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (ListingSortingTranslationBasicStruct $listingSortingTranslation) {
            return $listingSortingTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): ListingSortingTranslationBasicCollection
    {
        return $this->filter(function (ListingSortingTranslationBasicStruct $listingSortingTranslation) use ($uuid) {
            return $listingSortingTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ListingSortingTranslationBasicStruct::class;
    }
}
