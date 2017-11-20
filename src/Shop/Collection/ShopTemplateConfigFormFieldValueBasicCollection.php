<?php declare(strict_types=1);

namespace Shopware\Shop\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Shop\Struct\ShopTemplateConfigFormFieldValueBasicStruct;

class ShopTemplateConfigFormFieldValueBasicCollection extends EntityCollection
{
    /**
     * @var ShopTemplateConfigFormFieldValueBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ShopTemplateConfigFormFieldValueBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ShopTemplateConfigFormFieldValueBasicStruct
    {
        return parent::current();
    }

    public function getShopTemplateConfigFormFieldUuids(): array
    {
        return $this->fmap(function (ShopTemplateConfigFormFieldValueBasicStruct $shopTemplateConfigFormFieldValue) {
            return $shopTemplateConfigFormFieldValue->getShopTemplateConfigFormFieldUuid();
        });
    }

    public function filterByShopTemplateConfigFormFieldUuid(string $uuid): ShopTemplateConfigFormFieldValueBasicCollection
    {
        return $this->filter(function (ShopTemplateConfigFormFieldValueBasicStruct $shopTemplateConfigFormFieldValue) use ($uuid) {
            return $shopTemplateConfigFormFieldValue->getShopTemplateConfigFormFieldUuid() === $uuid;
        });
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (ShopTemplateConfigFormFieldValueBasicStruct $shopTemplateConfigFormFieldValue) {
            return $shopTemplateConfigFormFieldValue->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): ShopTemplateConfigFormFieldValueBasicCollection
    {
        return $this->filter(function (ShopTemplateConfigFormFieldValueBasicStruct $shopTemplateConfigFormFieldValue) use ($uuid) {
            return $shopTemplateConfigFormFieldValue->getShopUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateConfigFormFieldValueBasicStruct::class;
    }
}
