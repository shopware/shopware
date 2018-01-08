<?php declare(strict_types=1);

namespace Shopware\Api\Config\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;

class ConfigFormFieldValueDetailStruct extends ConfigFormFieldValueBasicStruct
{
    /**
     * @var ConfigFormFieldBasicStruct
     */
    protected $configFormField;

    /**
     * @var ShopBasicStruct|null
     */
    protected $shop;

    public function getConfigFormField(): ConfigFormFieldBasicStruct
    {
        return $this->configFormField;
    }

    public function setConfigFormField(ConfigFormFieldBasicStruct $configFormField): void
    {
        $this->configFormField = $configFormField;
    }

    public function getShop(): ?ShopBasicStruct
    {
        return $this->shop;
    }

    public function setShop(?ShopBasicStruct $shop): void
    {
        $this->shop = $shop;
    }
}
