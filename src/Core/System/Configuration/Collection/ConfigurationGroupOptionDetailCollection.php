<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Collection;

use Shopware\System\Configuration\Struct\ConfigurationGroupOptionDetailStruct;

class ConfigurationGroupOptionDetailCollection extends ConfigurationGroupOptionBasicCollection
{
    /**
     * @var ConfigurationGroupOptionDetailStruct[]
     */
    protected $elements = [];

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

    public function getTranslations(): ConfigurationGroupOptionTranslationBasicCollection
    {
        $collection = new ConfigurationGroupOptionTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupOptionDetailStruct::class;
    }
}
