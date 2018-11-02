<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SeoUrlCollection extends EntityCollection
{
    /**
     * @var SeoUrlStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? SeoUrlStruct
    {
        return parent::get($id);
    }

    public function current(): SeoUrlStruct
    {
        return parent::current();
    }

    public function getApplicationIds(): array
    {
        return $this->fmap(function (SeoUrlStruct $seoUrl) {
            return $seoUrl->getSalesChannelId();
        });
    }

    public function filterByApplicationId(string $id): SeoUrlCollection
    {
        return $this->filter(function (SeoUrlStruct $seoUrl) use ($id) {
            return $seoUrl->getSalesChannelId() === $id;
        });
    }

    public function getByPathInfo(string $pathInfo): ?SeoUrlStruct
    {
        foreach ($this->elements as $element) {
            if ($element->getPathInfo() === $pathInfo) {
                return $element;
            }
        }

        return null;
    }

    public function getBySeoPathInfo(string $seoPathInfo): ?SeoUrlStruct
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
        return $this->fmap(function (SeoUrlStruct $seoUrl) {
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
        return SeoUrlStruct::class;
    }
}
