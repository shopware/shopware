<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ListingSortingTranslationCollection extends EntityCollection
{
    /**
     * @var ListingSortingTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? ListingSortingTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): ListingSortingTranslationEntity
    {
        return parent::current();
    }

    public function getListingSortingIds(): array
    {
        return $this->fmap(function (ListingSortingTranslationEntity $listingSortingTranslation) {
            return $listingSortingTranslation->getListingSortingId();
        });
    }

    public function filterByListingSortingId(string $id): self
    {
        return $this->filter(function (ListingSortingTranslationEntity $listingSortingTranslation) use ($id) {
            return $listingSortingTranslation->getListingSortingId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ListingSortingTranslationEntity $listingSortingTranslation) {
            return $listingSortingTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ListingSortingTranslationEntity $listingSortingTranslation) use ($id) {
            return $listingSortingTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ListingSortingTranslationEntity::class;
    }
}
