<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\Storefront\Api\Seo\Struct\SeoUrlBasicStruct;

class SeoUrlBasicCollection extends EntityCollection
{
    /**
     * @var SeoUrlBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? SeoUrlBasicStruct
    {
        return parent::get($id);
    }

    public function current(): SeoUrlBasicStruct
    {
        return parent::current();
    }

    public function getApplicationIds(): array
    {
        return $this->fmap(function (SeoUrlBasicStruct $seoUrl) {
            return $seoUrl->getApplicationId();
        });
    }

    public function filterByApplicationId(string $id): SeoUrlBasicCollection
    {
        return $this->filter(function (SeoUrlBasicStruct $seoUrl) use ($id) {
            return $seoUrl->getApplicationId() === $id;
        });
    }

    public function getByPathInfo(string $pathInfo): ?SeoUrlBasicStruct
    {
        foreach ($this->elements as $element) {
            if ($element->getPathInfo() === $pathInfo) {
                return $element;
            }
        }

        return null;
    }

    public function getBySeoPathInfo(string $seoPathInfo): ?SeoUrlBasicStruct
    {
        foreach ($this->elements as $element) {
            if ($element->getSeoPathInfo() === $seoPathInfo) {
                return $element;
            }
        }

        return null;
    }

    public function getForeignKeys()
    {
        return $this->fmap(function (SeoUrlBasicStruct $seoUrl) {
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
        return SeoUrlBasicStruct::class;
    }
}
