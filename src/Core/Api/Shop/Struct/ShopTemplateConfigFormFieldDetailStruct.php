<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldValueBasicCollection;

class ShopTemplateConfigFormFieldDetailStruct extends ShopTemplateConfigFormFieldBasicStruct
{
    /**
     * @var ShopTemplateBasicStruct
     */
    protected $shopTemplate;

    /**
     * @var ShopTemplateConfigFormBasicStruct
     */
    protected $shopTemplateConfigForm;

    /**
     * @var ShopTemplateConfigFormFieldValueBasicCollection
     */
    protected $values;

    public function __construct()
    {
        $this->values = new ShopTemplateConfigFormFieldValueBasicCollection();
    }

    public function getShopTemplate(): ShopTemplateBasicStruct
    {
        return $this->shopTemplate;
    }

    public function setShopTemplate(ShopTemplateBasicStruct $shopTemplate): void
    {
        $this->shopTemplate = $shopTemplate;
    }

    public function getShopTemplateConfigForm(): ShopTemplateConfigFormBasicStruct
    {
        return $this->shopTemplateConfigForm;
    }

    public function setShopTemplateConfigForm(ShopTemplateConfigFormBasicStruct $shopTemplateConfigForm): void
    {
        $this->shopTemplateConfigForm = $shopTemplateConfigForm;
    }

    public function getValues(): ShopTemplateConfigFormFieldValueBasicCollection
    {
        return $this->values;
    }

    public function setValues(ShopTemplateConfigFormFieldValueBasicCollection $values): void
    {
        $this->values = $values;
    }
}
