<?php declare(strict_types=1);

namespace Shopware\Core\Content\Navigation\Aggregate\NavigationTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                             add(NavigationTranslationEntity $entity)
 * @method void                             set(string $key, NavigationTranslationEntity $entity)
 * @method NavigationTranslationEntity[]    getIterator()
 * @method NavigationTranslationEntity[]    getElements()
 * @method NavigationTranslationEntity|null get(string $key)
 * @method NavigationTranslationEntity|null first()
 * @method NavigationTranslationEntity|null last()
 */
class NavigationTranslationCollection extends EntityCollection
{
    public function getNavigationIds(): array
    {
        return $this->fmap(function (NavigationTranslationEntity $navigationTranslation) {
            return $navigationTranslation->getNavigationId();
        });
    }

    public function filterByNavigationId(string $id): self
    {
        return $this->filter(function (NavigationTranslationEntity $navigationTranslation) use ($id) {
            return $navigationTranslation->getNavigationId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (NavigationTranslationEntity $navigationTranslation) {
            return $navigationTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (NavigationTranslationEntity $navigationTranslation) use ($id) {
            return $navigationTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return NavigationTranslationEntity::class;
    }
}
