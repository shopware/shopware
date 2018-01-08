<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Shop\Struct\ShopTemplateConfigFormFieldBasicStruct;

class ShopTemplateConfigFormFieldBasicCollection extends EntityCollection
{
    /**
     * @var ShopTemplateConfigFormFieldBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ShopTemplateConfigFormFieldBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ShopTemplateConfigFormFieldBasicStruct
    {
        return parent::current();
    }

    public function getShopTemplateUuids(): array
    {
        return $this->fmap(function (ShopTemplateConfigFormFieldBasicStruct $shopTemplateConfigFormField) {
            return $shopTemplateConfigFormField->getShopTemplateUuid();
        });
    }

    public function filterByShopTemplateUuid(string $uuid): self
    {
        return $this->filter(function (ShopTemplateConfigFormFieldBasicStruct $shopTemplateConfigFormField) use ($uuid) {
            return $shopTemplateConfigFormField->getShopTemplateUuid() === $uuid;
        });
    }

    public function getShopTemplateConfigFormUuids(): array
    {
        return $this->fmap(function (ShopTemplateConfigFormFieldBasicStruct $shopTemplateConfigFormField) {
            return $shopTemplateConfigFormField->getShopTemplateConfigFormUuid();
        });
    }

    public function filterByShopTemplateConfigFormUuid(string $uuid): self
    {
        return $this->filter(function (ShopTemplateConfigFormFieldBasicStruct $shopTemplateConfigFormField) use ($uuid) {
            return $shopTemplateConfigFormField->getShopTemplateConfigFormUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateConfigFormFieldBasicStruct::class;
    }
}
