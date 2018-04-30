<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

class ShopTemplateConfigPresetDetailStruct extends ShopTemplateConfigPresetBasicStruct
{
    /**
     * @var ShopTemplateBasicStruct
     */
    protected $shopTemplate;

    public function getShopTemplate(): ShopTemplateBasicStruct
    {
        return $this->shopTemplate;
    }

    public function setShopTemplate(ShopTemplateBasicStruct $shopTemplate): void
    {
        $this->shopTemplate = $shopTemplate;
    }
}
