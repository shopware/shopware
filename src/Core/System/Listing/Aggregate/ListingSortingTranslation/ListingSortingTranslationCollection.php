<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ListingSortingTranslationCollection extends EntityCollection
{
    /**
     * @var ListingSortingTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ListingSortingTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): ListingSortingTranslationStruct
    {
        return parent::current();
    }

    public function getListingSortingIds(): array
    {
        return $this->fmap(function (ListingSortingTranslationStruct $listingSortingTranslation) {
            return $listingSortingTranslation->getListingSortingId();
        });
    }

    public function filterByListingSortingId(string $id): self
    {
        return $this->filter(function (ListingSortingTranslationStruct $listingSortingTranslation) use ($id) {
            return $listingSortingTranslation->getListingSortingId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ListingSortingTranslationStruct $listingSortingTranslation) {
            return $listingSortingTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ListingSortingTranslationStruct $listingSortingTranslation) use ($id) {
            return $listingSortingTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ListingSortingTranslationStruct::class;
    }
}
