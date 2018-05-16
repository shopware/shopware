<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection;

use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Struct\ConfigurationGroupOptionTranslationBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

class ConfigurationGroupOptionTranslationBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Struct\ConfigurationGroupOptionTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigurationGroupOptionTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigurationGroupOptionTranslationBasicStruct
    {
        return parent::current();
    }

    public function getConfigurationGroupOptionIds(): array
    {
        return $this->fmap(function (ConfigurationGroupOptionTranslationBasicStruct $configurationGroupOptionTranslation) {
            return $configurationGroupOptionTranslation->getConfigurationGroupOptionId();
        });
    }

    public function filterByConfigurationGroupOptionId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupOptionTranslationBasicStruct $configurationGroupOptionTranslation) use ($id) {
            return $configurationGroupOptionTranslation->getConfigurationGroupOptionId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ConfigurationGroupOptionTranslationBasicStruct $configurationGroupOptionTranslation) {
            return $configurationGroupOptionTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupOptionTranslationBasicStruct $configurationGroupOptionTranslation) use ($id) {
            return $configurationGroupOptionTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupOptionTranslationBasicStruct::class;
    }
}
