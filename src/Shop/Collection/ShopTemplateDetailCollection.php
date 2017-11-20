<?php declare(strict_types=1);

namespace Shopware\Shop\Collection;

use Shopware\Plugin\Collection\PluginBasicCollection;
use Shopware\Shop\Struct\ShopTemplateDetailStruct;

class ShopTemplateDetailCollection extends ShopTemplateBasicCollection
{
    /**
     * @var ShopTemplateDetailStruct[]
     */
    protected $elements = [];

    public function getPlugins(): PluginBasicCollection
    {
        return new PluginBasicCollection(
            $this->fmap(function (ShopTemplateDetailStruct $shopTemplate) {
                return $shopTemplate->getPlugin();
            })
        );
    }

    public function getParents(): ShopTemplateBasicCollection
    {
        return new ShopTemplateBasicCollection(
            $this->fmap(function (ShopTemplateDetailStruct $shopTemplate) {
                return $shopTemplate->getParent();
            })
        );
    }

    public function getShopUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShops()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getShops(): ShopBasicCollection
    {
        $collection = new ShopBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getShops()->getElements());
        }

        return $collection;
    }

    public function getConfigFormUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getConfigForms()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getConfigForms(): ShopTemplateConfigFormBasicCollection
    {
        $collection = new ShopTemplateConfigFormBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getConfigForms()->getElements());
        }

        return $collection;
    }

    public function getConfigFormFieldUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getConfigFormFields()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getConfigFormFields(): ShopTemplateConfigFormFieldBasicCollection
    {
        $collection = new ShopTemplateConfigFormFieldBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getConfigFormFields()->getElements());
        }

        return $collection;
    }

    public function getConfigPresetUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getConfigPresets()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getConfigPresets(): ShopTemplateConfigPresetBasicCollection
    {
        $collection = new ShopTemplateConfigPresetBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getConfigPresets()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateDetailStruct::class;
    }
}
