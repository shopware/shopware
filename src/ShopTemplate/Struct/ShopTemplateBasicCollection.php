<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Struct;

use Shopware\Framework\Struct\Collection;

class ShopTemplateBasicCollection extends Collection
{
    /**
     * @var ShopTemplateBasicStruct[]
     */
    protected $elements = [];

    public function add(ShopTemplateBasicStruct $shopTemplate): void
    {
        $key = $this->getKey($shopTemplate);
        $this->elements[$key] = $shopTemplate;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ShopTemplateBasicStruct $shopTemplate): void
    {
        parent::doRemoveByKey($this->getKey($shopTemplate));
    }

    public function exists(ShopTemplateBasicStruct $shopTemplate): bool
    {
        return parent::has($this->getKey($shopTemplate));
    }

    public function getList(array $uuids): ShopTemplateBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ShopTemplateBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ShopTemplateBasicStruct $shopTemplate) {
            return $shopTemplate->getUuid();
        });
    }

    public function merge(ShopTemplateBasicCollection $collection)
    {
        /** @var ShopTemplateBasicStruct $shopTemplate */
        foreach ($collection as $shopTemplate) {
            if ($this->has($this->getKey($shopTemplate))) {
                continue;
            }
            $this->add($shopTemplate);
        }
    }

    public function getPluginUuids(): array
    {
        return $this->fmap(function (ShopTemplateBasicStruct $shopTemplate) {
            return $shopTemplate->getPluginUuid();
        });
    }

    public function filterByPluginUuid(string $uuid): ShopTemplateBasicCollection
    {
        return $this->filter(function (ShopTemplateBasicStruct $shopTemplate) use ($uuid) {
            return $shopTemplate->getPluginUuid() === $uuid;
        });
    }

    public function getParentUuids(): array
    {
        return $this->fmap(function (ShopTemplateBasicStruct $shopTemplate) {
            return $shopTemplate->getParentUuid();
        });
    }

    public function filterByParentUuid(string $uuid): ShopTemplateBasicCollection
    {
        return $this->filter(function (ShopTemplateBasicStruct $shopTemplate) use ($uuid) {
            return $shopTemplate->getParentUuid() === $uuid;
        });
    }

    protected function getKey(ShopTemplateBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
