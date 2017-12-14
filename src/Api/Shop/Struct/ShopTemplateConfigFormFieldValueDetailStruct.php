<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

class ShopTemplateConfigFormFieldValueDetailStruct extends ShopTemplateConfigFormFieldValueBasicStruct
{
    /**
     * @var ShopTemplateConfigFormFieldBasicStruct
     */
    protected $shopTemplateConfigFormField;

    /**
     * @var ShopBasicStruct
     */
    protected $shop;

    public function getShopTemplateConfigFormField(): ShopTemplateConfigFormFieldBasicStruct
    {
        return $this->shopTemplateConfigFormField;
    }

    public function setShopTemplateConfigFormField(ShopTemplateConfigFormFieldBasicStruct $shopTemplateConfigFormField): void
    {
        $this->shopTemplateConfigFormField = $shopTemplateConfigFormField;
    }

    public function getShop(): ShopBasicStruct
    {
        return $this->shop;
    }

    public function setShop(ShopBasicStruct $shop): void
    {
        $this->shop = $shop;
    }
}
