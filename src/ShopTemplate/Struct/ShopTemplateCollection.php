<?php

namespace Shopware\ShopTemplate\Struct;

use Shopware\Framework\Struct\Collection;

class ShopTemplateCollection extends Collection
{
    /**
     * @var ShopTemplate[]
     */
    protected $elements = [];

    public function add(ShopTemplate $shopTemplate): void
    {
        $key = $this->getKey($shopTemplate);
        $this->elements[$key] = $shopTemplate;
    }

    public function remove(int $id): void
    {
        parent::doRemoveByKey($id);
    }

    public function removeElement(ShopTemplate $shopTemplate): void
    {
        parent::doRemoveByKey($this->getKey($shopTemplate));
    }

    public function exists(ShopTemplate $shopTemplate): bool
    {
        return parent::has($this->getKey($shopTemplate));
    }

    public function get(int $id): ? ShopTemplate
    {
        if ($this->has($id)) {
            return $this->elements[$id];
        }

        return null;
    }

    public function getIds(): array
    {
        return $this->fmap(function(ShopTemplate $shopTemplate) {
            return $shopTemplate->getId();
        });
    }

    protected function getKey(ShopTemplate $element): int
    {
        return $element->getId();
    }
}