<?php declare(strict_types=1);

namespace Shopware\Config\Collection;

use Shopware\Config\Struct\ConfigFormFieldValueDetailStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

class ConfigFormFieldValueDetailCollection extends ConfigFormFieldValueBasicCollection
{
    /**
     * @var ConfigFormFieldValueDetailStruct[]
     */
    protected $elements = [];

    public function getShops(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (ConfigFormFieldValueDetailStruct $configFormFieldValue) {
                return $configFormFieldValue->getShop();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldValueDetailStruct::class;
    }
}
