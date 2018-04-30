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

    public function get(string $id): ? ShopTemplateConfigFormFieldBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ShopTemplateConfigFormFieldBasicStruct
    {
        return parent::current();
    }

    public function getShopTemplateIds(): array
    {
        return $this->fmap(function (ShopTemplateConfigFormFieldBasicStruct $shopTemplateConfigFormField) {
            return $shopTemplateConfigFormField->getShopTemplateId();
        });
    }

    public function filterByShopTemplateId(string $id): self
    {
        return $this->filter(function (ShopTemplateConfigFormFieldBasicStruct $shopTemplateConfigFormField) use ($id) {
            return $shopTemplateConfigFormField->getShopTemplateId() === $id;
        });
    }

    public function getShopTemplateConfigFormIds(): array
    {
        return $this->fmap(function (ShopTemplateConfigFormFieldBasicStruct $shopTemplateConfigFormField) {
            return $shopTemplateConfigFormField->getShopTemplateConfigFormId();
        });
    }

    public function filterByShopTemplateConfigFormId(string $id): self
    {
        return $this->filter(function (ShopTemplateConfigFormFieldBasicStruct $shopTemplateConfigFormField) use ($id) {
            return $shopTemplateConfigFormField->getShopTemplateConfigFormId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateConfigFormFieldBasicStruct::class;
    }
}
