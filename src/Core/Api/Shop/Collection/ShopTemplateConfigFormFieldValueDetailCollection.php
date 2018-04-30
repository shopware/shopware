<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Api\Shop\Struct\ShopTemplateConfigFormFieldValueDetailStruct;

class ShopTemplateConfigFormFieldValueDetailCollection extends ShopTemplateConfigFormFieldValueBasicCollection
{
    /**
     * @var ShopTemplateConfigFormFieldValueDetailStruct[]
     */
    protected $elements = [];

    public function getShopTemplateConfigFormFields(): ShopTemplateConfigFormFieldBasicCollection
    {
        return new ShopTemplateConfigFormFieldBasicCollection(
            $this->fmap(function (ShopTemplateConfigFormFieldValueDetailStruct $shopTemplateConfigFormFieldValue) {
                return $shopTemplateConfigFormFieldValue->getShopTemplateConfigFormField();
            })
        );
    }

    public function getShops(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (ShopTemplateConfigFormFieldValueDetailStruct $shopTemplateConfigFormFieldValue) {
                return $shopTemplateConfigFormFieldValue->getShop();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateConfigFormFieldValueDetailStruct::class;
    }
}
