<?php declare(strict_types=1);

namespace Shopware\Api\Config\Collection;

use Shopware\Api\Config\Struct\ConfigFormDetailStruct;
use Shopware\Api\Plugin\Collection\PluginBasicCollection;

class ConfigFormDetailCollection extends ConfigFormBasicCollection
{
    /**
     * @var ConfigFormDetailStruct[]
     */
    protected $elements = [];

    public function getParents(): ConfigFormBasicCollection
    {
        return new ConfigFormBasicCollection(
            $this->fmap(function (ConfigFormDetailStruct $configForm) {
                return $configForm->getParent();
            })
        );
    }

    public function getPlugins(): PluginBasicCollection
    {
        return new PluginBasicCollection(
            $this->fmap(function (ConfigFormDetailStruct $configForm) {
                return $configForm->getPlugin();
            })
        );
    }

    public function getChildrenIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getChildren()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getChildren(): ConfigFormBasicCollection
    {
        $collection = new ConfigFormBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getChildren()->getElements());
        }

        return $collection;
    }

    public function getFieldIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getFields()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getFields(): ConfigFormFieldBasicCollection
    {
        $collection = new ConfigFormFieldBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getFields()->getElements());
        }

        return $collection;
    }

    public function getTranslationIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getTranslations(): ConfigFormTranslationBasicCollection
    {
        $collection = new ConfigFormTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormDetailStruct::class;
    }
}
