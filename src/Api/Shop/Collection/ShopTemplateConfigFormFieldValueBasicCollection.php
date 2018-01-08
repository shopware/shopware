<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Shop\Struct\ShopTemplateConfigFormFieldValueBasicStruct;

class ShopTemplateConfigFormFieldValueBasicCollection extends EntityCollection
{
    /**
     * @var ShopTemplateConfigFormFieldValueBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ShopTemplateConfigFormFieldValueBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ShopTemplateConfigFormFieldValueBasicStruct
    {
        return parent::current();
    }

    public function getShopTemplateConfigFormFieldIds(): array
    {
        return $this->fmap(function (ShopTemplateConfigFormFieldValueBasicStruct $shopTemplateConfigFormFieldValue) {
            return $shopTemplateConfigFormFieldValue->getShopTemplateConfigFormFieldId();
        });
    }

    public function filterByShopTemplateConfigFormFieldId(string $id): self
    {
        return $this->filter(function (ShopTemplateConfigFormFieldValueBasicStruct $shopTemplateConfigFormFieldValue) use ($id) {
            return $shopTemplateConfigFormFieldValue->getShopTemplateConfigFormFieldId() === $id;
        });
    }

    public function getShopIds(): array
    {
        return $this->fmap(function (ShopTemplateConfigFormFieldValueBasicStruct $shopTemplateConfigFormFieldValue) {
            return $shopTemplateConfigFormFieldValue->getShopId();
        });
    }

    public function filterByShopId(string $id): self
    {
        return $this->filter(function (ShopTemplateConfigFormFieldValueBasicStruct $shopTemplateConfigFormFieldValue) use ($id) {
            return $shopTemplateConfigFormFieldValue->getShopId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateConfigFormFieldValueBasicStruct::class;
    }
}
