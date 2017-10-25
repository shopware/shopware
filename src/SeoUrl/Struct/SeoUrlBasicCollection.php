<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Struct;

use Shopware\Framework\Struct\Collection;

class SeoUrlBasicCollection extends Collection
{
    /**
     * @var SeoUrlBasicStruct[]
     */
    protected $elements = [];

    public function add(SeoUrlBasicStruct $seoUrl): void
    {
        $key = $this->getKey($seoUrl);
        $this->elements[$key] = $seoUrl;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(SeoUrlBasicStruct $seoUrl): void
    {
        parent::doRemoveByKey($this->getKey($seoUrl));
    }

    public function exists(SeoUrlBasicStruct $seoUrl): bool
    {
        return parent::has($this->getKey($seoUrl));
    }

    public function getList(array $uuids): SeoUrlBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? SeoUrlBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (SeoUrlBasicStruct $seoUrl) {
            return $seoUrl->getUuid();
        });
    }

    public function merge(SeoUrlBasicCollection $collection)
    {
        /** @var SeoUrlBasicStruct $seoUrl */
        foreach ($collection as $seoUrl) {
            if ($this->has($this->getKey($seoUrl))) {
                continue;
            }
            $this->add($seoUrl);
        }
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (SeoUrlBasicStruct $seoUrl) {
            return $seoUrl->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): SeoUrlBasicCollection
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

    public function current(): SeoUrlBasicStruct
    {
        return parent::current();
    }

    protected function getKey(SeoUrlBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
