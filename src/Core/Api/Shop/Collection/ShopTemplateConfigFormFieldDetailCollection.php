<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Api\Shop\Struct\ShopTemplateConfigFormFieldDetailStruct;

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

    public function getValueIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getValues()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
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
