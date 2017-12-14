<?php declare(strict_types=1);

namespace Shopware\Api\Config\Collection;

use Shopware\Api\Config\Struct\ConfigFormFieldDetailStruct;

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

    public function getTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getTranslations(): ConfigFormFieldTranslationBasicCollection
    {
        $collection = new ConfigFormFieldTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldDetailStruct::class;
    }
}
