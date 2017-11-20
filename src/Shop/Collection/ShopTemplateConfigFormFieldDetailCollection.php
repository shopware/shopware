<?php declare(strict_types=1);

namespace Shopware\Shop\Collection;

use Shopware\Shop\Struct\ShopTemplateConfigFormFieldDetailStruct;

class ShopTemplateConfigFormFieldDetailCollection extends ShopTemplateConfigFormFieldBasicCollection
{
    /**
     * @var ShopTemplateConfigFormFieldDetailStruct[]
     */
    protected $elements = [];

    public function getShopTemplates(): ShopTemplateBasicCollection
    {
        return new ShopTemplateBasicCollection(
            $this->fmap(function (ShopTemplateConfigFormFieldDetailStruct $shopTemplateConfigFormField) {
                return $shopTemplateConfigFormField->getShopTemplate();
            })
        );
    }

    public function getShopTemplateConfigForms(): ShopTemplateConfigFormBasicCollection
    {
        return new ShopTemplateConfigFormBasicCollection(
            $this->fmap(function (ShopTemplateConfigFormFieldDetailStruct $shopTemplateConfigFormField) {
                return $shopTemplateConfigFormField->getShopTemplateConfigForm();
            })
        );
    }

    public function getValueUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getValues()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getValues(): ShopTemplateConfigFormFieldValueBasicCollection
    {
        $collection = new ShopTemplateConfigFormFieldValueBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getValues()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateConfigFormFieldDetailStruct::class;
    }
}
