<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionTranslationBasicStruct;


class ConfigurationGroupOptionTranslationBasicCollection extends EntityCollection
{
    /**
     * @var ConfigurationGroupOptionTranslationBasicStruct[]
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
        return $this->fmap(function(ConfigurationGroupOptionTranslationBasicStruct $configurationGroupOptionTranslation) {
            return $configurationGroupOptionTranslation->getConfigurationGroupOptionId();
        });
    }

    public function filterByConfigurationGroupOptionId(string $id): ConfigurationGroupOptionTranslationBasicCollection
    {
        return $this->filter(function(ConfigurationGroupOptionTranslationBasicStruct $configurationGroupOptionTranslation) use ($id) {
            return $configurationGroupOptionTranslation->getConfigurationGroupOptionId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function(ConfigurationGroupOptionTranslationBasicStruct $configurationGroupOptionTranslation) {
            return $configurationGroupOptionTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): ConfigurationGroupOptionTranslationBasicCollection
    {
        return $this->filter(function(ConfigurationGroupOptionTranslationBasicStruct $configurationGroupOptionTranslation) use ($id) {
            return $configurationGroupOptionTranslation->getLanguageId() === $id;
        });
    }

    public function getLanguageVersionIds(): array
    {
        return $this->fmap(function(ConfigurationGroupOptionTranslationBasicStruct $configurationGroupOptionTranslation) {
            return $configurationGroupOptionTranslation->getLanguageVersionId();
        });
    }

    public function filterByLanguageVersionId(string $id): ConfigurationGroupOptionTranslationBasicCollection
    {
        return $this->filter(function(ConfigurationGroupOptionTranslationBasicStruct $configurationGroupOptionTranslation) use ($id) {
            return $configurationGroupOptionTranslation->getLanguageVersionId() === $id;
        });
    }

    public function getVersionIds(): array
    {
        return $this->fmap(function(ConfigurationGroupOptionTranslationBasicStruct $configurationGroupOptionTranslation) {
            return $configurationGroupOptionTranslation->getVersionId();
        });
    }

    public function filterByVersionId(string $id): ConfigurationGroupOptionTranslationBasicCollection
    {
        return $this->filter(function(ConfigurationGroupOptionTranslationBasicStruct $configurationGroupOptionTranslation) use ($id) {
            return $configurationGroupOptionTranslation->getVersionId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupOptionTranslationBasicStruct::class;
    }
}