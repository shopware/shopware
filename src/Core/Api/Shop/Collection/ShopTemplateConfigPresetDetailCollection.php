<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Api\Shop\Struct\ShopTemplateConfigPresetDetailStruct;

class ShopTemplateConfigPresetDetailCollection extends ShopTemplateConfigPresetBasicCollection
{
    /**
     * @var ShopTemplateConfigPresetDetailStruct[]
     */
    protected $elements = [];

    public function getShopTemplates(): ShopTemplateBasicCollection
    {
        return new ShopTemplateBasicCollection(
            $this->fmap(function (ShopTemplateConfigPresetDetailStruct $shopTemplateConfigPreset) {
                return $shopTemplateConfigPreset->getShopTemplate();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateConfigPresetDetailStruct::class;
    }
}
