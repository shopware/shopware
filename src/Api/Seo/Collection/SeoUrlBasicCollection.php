<?php declare(strict_types=1);

namespace Shopware\Api\Seo\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Seo\Struct\SeoUrlBasicStruct;

class SeoUrlBasicCollection extends EntityCollection
{
    /**
     * @var SeoUrlBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? SeoUrlBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): SeoUrlBasicStruct
    {
        return parent::current();
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (SeoUrlBasicStruct $seoUrl) {
            return $seoUrl->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): self
    {
        return $this->filter(function (SeoUrlBasicStruct $seoUrl) use ($uuid) {
            return $seoUrl->getShopUuid() === $uuid;
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
