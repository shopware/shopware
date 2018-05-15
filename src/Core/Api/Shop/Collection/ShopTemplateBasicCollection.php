<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\Api\Shop\Struct\ShopTemplateBasicStruct;

class ShopTemplateBasicCollection extends EntityCollection
{
    /**
     * @var ShopTemplateBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ShopTemplateBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ShopTemplateBasicStruct
    {
        return parent::current();
    }

    public function getPluginIds(): array
    {
        return $this->fmap(function (ShopTemplateBasicStruct $shopTemplate) {
            return $shopTemplate->getPluginId();
        });
    }

    public function filterByPluginId(string $id): self
    {
        return $this->filter(function (ShopTemplateBasicStruct $shopTemplate) use ($id) {
            return $shopTemplate->getPluginId() === $id;
        });
    }

    public function getParentIds(): array
    {
        return $this->fmap(function (ShopTemplateBasicStruct $shopTemplate) {
            return $shopTemplate->getParentId();
        });
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(function (ShopTemplateBasicStruct $shopTemplate) use ($id) {
            return $shopTemplate->getParentId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateBasicStruct::class;
    }
}
