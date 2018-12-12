<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SeoUrlCollection extends EntityCollection
{
    /**
     * @var SeoUrlEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? SeoUrlEntity
    {
        return parent::get($id);
    }

    public function current(): SeoUrlEntity
    {
        return parent::current();
    }

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
        foreach ($this->elements as $element) {
            if ($element->getPathInfo() === $pathInfo) {
                return $element;
            }
        }

        return null;
    }

    public function getBySeoPathInfo(string $seoPathInfo): ?SeoUrlEntity
    {
        foreach ($this->elements as $element) {
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
        foreach ($this->elements as $element) {
            if ($element->getForeignKey() === $foreignKey && $element->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    public function hasPathInfo(string $pathInfo): bool
    {
        foreach ($this->elements as $element) {
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
