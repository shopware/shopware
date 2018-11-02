<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ConfigurationGroupOptionTranslationCollection extends EntityCollection
{
    /**
     * @var ConfigurationGroupOptionTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigurationGroupOptionTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigurationGroupOptionTranslationStruct
    {
        return parent::current();
    }

    public function getConfigurationGroupOptionIds(): array
    {
        return $this->fmap(function (ConfigurationGroupOptionTranslationStruct $configurationGroupOptionTranslation) {
            return $configurationGroupOptionTranslation->getConfigurationGroupOptionId();
        });
    }

    public function filterByConfigurationGroupOptionId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupOptionTranslationStruct $configurationGroupOptionTranslation) use ($id) {
            return $configurationGroupOptionTranslation->getConfigurationGroupOptionId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ConfigurationGroupOptionTranslationStruct $configurationGroupOptionTranslation) {
            return $configurationGroupOptionTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupOptionTranslationStruct $configurationGroupOptionTranslation) use ($id) {
            return $configurationGroupOptionTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupOptionTranslationStruct::class;
    }
}
