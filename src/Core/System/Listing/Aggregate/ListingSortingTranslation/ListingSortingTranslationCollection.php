<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                 add(ListingSortingTranslationEntity $entity)
 * @method void                                 set(string $key, ListingSortingTranslationEntity $entity)
 * @method ListingSortingTranslationEntity[]    getIterator()
 * @method ListingSortingTranslationEntity[]    getElements()
 * @method ListingSortingTranslationEntity|null get(string $key)
 * @method ListingSortingTranslationEntity|null first()
 * @method ListingSortingTranslationEntity|null last()
 */
class ListingSortingTranslationCollection extends EntityCollection
{
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
