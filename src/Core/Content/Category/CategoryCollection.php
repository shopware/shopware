<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Util\AfterSort;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CategoryEntity>
 */
#[Package('inventory')]
class CategoryCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getParentIds(): array
    {
        return $this->fmap(fn (CategoryEntity $category) => $category->getParentId());
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(fn (CategoryEntity $category) => $category->getParentId() === $id);
    }

    public function find(string $id): ?CategoryEntity
    {
        return $this->_find($this, $id);
    }

    private function _find(CategoryCollection $elements, string $id): ?CategoryEntity
    {
        foreach ($elements as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
            if ($item->getChildren() === null) {
                continue;
            }
            $result = $this->_find($item->getChildren(), $id);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @return array<string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(fn (CategoryEntity $category) => $category->getMediaId());
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(fn (CategoryEntity $category) => $category->getMediaId() === $id);
    }

    public function sortByPosition(): self
    {
        $this->elements = AfterSort::sort($this->elements, 'afterCategoryId');

        return $this;
    }

    public function sortByName(): self
    {
        $this->sort(fn (CategoryEntity $a, CategoryEntity $b) => strnatcasecmp((string) $a->getTranslated()['name'], (string) $b->getTranslated()['name']));

        return $this;
    }

    public function getApiAlias(): string
    {
        return 'category_collection';
    }

    protected function getExpectedClass(): string
    {
        return CategoryEntity::class;
    }
}
