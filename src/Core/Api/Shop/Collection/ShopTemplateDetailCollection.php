<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Framework\Plugin\Collection\PluginBasicCollection;
use Shopware\Api\Shop\Struct\ShopTemplateDetailStruct;

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

    public function getConfigFormIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getConfigForms()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getConfigForms(): ShopTemplateConfigFormBasicCollection
    {
        $collection = new ShopTemplateConfigFormBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getConfigForms()->getElements());
        }

        return $collection;
    }

    public function getConfigFormFieldIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getConfigFormFields()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getConfigFormFields(): ShopTemplateConfigFormFieldBasicCollection
    {
        $collection = new ShopTemplateConfigFormFieldBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getConfigFormFields()->getElements());
        }

        return $collection;
    }

    public function getConfigPresetIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getConfigPresets()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
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
