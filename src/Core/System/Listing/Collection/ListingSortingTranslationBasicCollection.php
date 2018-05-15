<?php declare(strict_types=1);

namespace Shopware\System\Listing\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\Listing\Struct\ListingSortingTranslationBasicStruct;

class ListingSortingTranslationBasicCollection extends EntityCollection
{
    /**
     * @var ListingSortingTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ListingSortingTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ListingSortingTranslationBasicStruct
    {
        return parent::current();
    }

    public function getListingSortingIds(): array
    {
        return $this->fmap(function (ListingSortingTranslationBasicStruct $listingSortingTranslation) {
            return $listingSortingTranslation->getListingSortingId();
        });
    }

    public function filterByListingSortingId(string $id): self
    {
        return $this->filter(function (ListingSortingTranslationBasicStruct $listingSortingTranslation) use ($id) {
            return $listingSortingTranslation->getListingSortingId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ListingSortingTranslationBasicStruct $listingSortingTranslation) {
            return $listingSortingTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ListingSortingTranslationBasicStruct $listingSortingTranslation) use ($id) {
            return $listingSortingTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ListingSortingTranslationBasicStruct::class;
    }
}
