<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(SeoUrlEntity $entity)
 * @method void              set(string $key, SeoUrlEntity $entity)
 * @method SeoUrlEntity[]    getIterator()
 * @method SeoUrlEntity[]    getElements()
 * @method SeoUrlEntity|null get(string $key)
 * @method SeoUrlEntity|null first()
 * @method SeoUrlEntity|null last()
 */
class SeoUrlCollection extends EntityCollection
{
    public function getApplicationIds(): array
    {
        return $this->fmap(function (SeoUrlEntity $seoUrl) {
            return $seoUrl->getSalesChannelId();
        });
    }

    public function filterByApplicationId(string $id): SeoUrlCollection
    {
        return $this->filter(function (SeoUrlEntity $seoUrl) use ($id) {
            return $seoUrl->getSalesChannelId() === $id;
        });
    }

    public function getByPathInfo(string $pathInfo): ?SeoUrlEntity
    {
        foreach ($this->getIterator() as $element) {
            if ($element->getPathInfo() === $pathInfo) {
                return $element;
            }
        }

        return null;
    }

    public function getBySeoPathInfo(string $seoPathInfo): ?SeoUrlEntity
    {
        foreach ($this->getIterator() as $element) {
            if ($element->getSeoPathInfo() === $seoPathInfo) {
                return $element;
            }
        }

        return null;
    }

    public function getForeignKeys(): array
    {
        return $this->fmap(function (SeoUrlEntity $seoUrl) {
            return $seoUrl->getForeignKey();
        });
    }

    public function hasForeignKey(string $name, string $foreignKey): bool
    {
        foreach ($this->getIterator() as $element) {
            if ($element->getForeignKey() === $foreignKey && $element->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    public function hasPathInfo(string $pathInfo): bool
    {
        foreach ($this->getIterator() as $element) {
            if ($element->getPathInfo() === $pathInfo) {
                return true;
            }
        }

        return false;
    }

    protected function getExpectedClass(): string
    {
        return SeoUrlEntity::class;
    }
}
