<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Shop\Struct\ShopTemplateBasicStruct;

class ShopTemplateBasicCollection extends EntityCollection
{
    /**
     * @var ShopTemplateBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ShopTemplateBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ShopTemplateBasicStruct
    {
        return parent::current();
    }

    public function getPluginUuids(): array
    {
        return $this->fmap(function (ShopTemplateBasicStruct $shopTemplate) {
            return $shopTemplate->getPluginUuid();
        });
    }

    public function filterByPluginUuid(string $uuid): self
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

    public function filterByParentUuid(string $uuid): self
    {
        return $this->filter(function (ShopTemplateBasicStruct $shopTemplate) use ($uuid) {
            return $shopTemplate->getParentUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateBasicStruct::class;
    }
}
