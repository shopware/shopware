<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Collection;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Collection\ConfigurationGroupOptionBasicCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Collection\ConfigurationGroupTranslationBasicCollection;
use Shopware\Core\Content\Configuration\Struct\ConfigurationGroupDetailStruct;

class ConfigurationGroupDetailCollection extends ConfigurationGroupBasicCollection
{
    /**
     * @var ConfigurationGroupDetailStruct[]
     */
    protected $elements = [];

    public function getOptionIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOptions()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getOptions(): ConfigurationGroupOptionBasicCollection
    {
        $collection = new ConfigurationGroupOptionBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOptions()->getElements());
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

    public function getTranslations(): ConfigurationGroupTranslationBasicCollection
    {
        $collection = new ConfigurationGroupTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupDetailStruct::class;
    }
}
