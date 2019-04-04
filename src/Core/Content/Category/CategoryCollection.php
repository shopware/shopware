<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Util\AfterSort;

/**
 * @method void                add(CategoryEntity $entity)
 * @method void                set(string $key, CategoryEntity $entity)
 * @method CategoryEntity[]    getIterator()
 * @method CategoryEntity[]    getElements()
 * @method CategoryEntity|null get(string $key)
 * @method CategoryEntity|null first()
 * @method CategoryEntity|null last()
 */
class CategoryCollection extends EntityCollection
{
    public function getParentIds(): array
    {
        return $this->fmap(function (CategoryEntity $category) {
            return $category->getParentId();
        });
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(function (CategoryEntity $category) use ($id) {
            return $category->getParentId() === $id;
        });
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (CategoryEntity $category) {
            return $category->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (CategoryEntity $category) use ($id) {
            return $category->getMediaId() === $id;
        });
    }

    public function sortByPosition(): self
    {
        $this->elements = AfterSort::sort($this->elements, 'afterCategoryId');

        return $this;
    }

    public function sortByName(): self
    {
        $this->sort(function (CategoryEntity $a, CategoryEntity $b) {
            return strnatcasecmp($a->getTranslated()['name'], $b->getTranslated()['name']);
        });

        return $this;
    }

    protected function getExpectedClass(): string
    {
        return CategoryEntity::class;
    }
}
