<?php declare(strict_types=1);

namespace Shopware\System\Config\Collection;

use Shopware\System\Config\Struct\ConfigFormFieldDetailStruct;

class ConfigFormFieldDetailCollection extends ConfigFormFieldBasicCollection
{
    /**
     * @var ConfigFormFieldDetailStruct[]
     */
    protected $elements = [];

    public function getConfigForms(): ConfigFormBasicCollection
    {
        return new ConfigFormBasicCollection(
            $this->fmap(function (ConfigFormFieldDetailStruct $configFormField) {
                return $configFormField->getConfigForm();
            })
        );
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

    public function getTranslations(): ConfigFormFieldTranslationBasicCollection
    {
        $collection = new ConfigFormFieldTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
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

    public function getValues(): ConfigFormFieldValueBasicCollection
    {
        $collection = new ConfigFormFieldValueBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getValues()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldDetailStruct::class;
    }
}
