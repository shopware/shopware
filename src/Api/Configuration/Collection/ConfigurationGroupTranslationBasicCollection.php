<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Configuration\Struct\ConfigurationGroupTranslationBasicStruct;


class ConfigurationGroupTranslationBasicCollection extends EntityCollection
{
    /**
     * @var ConfigurationGroupTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigurationGroupTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigurationGroupTranslationBasicStruct
    {
        return parent::current();
    }


    public function getConfigurationGroupIds(): array
    {
        return $this->fmap(function(ConfigurationGroupTranslationBasicStruct $configurationGroupTranslation) {
            return $configurationGroupTranslation->getConfigurationGroupId();
        });
    }

    public function filterByConfigurationGroupId(string $id): ConfigurationGroupTranslationBasicCollection
    {
        return $this->filter(function(ConfigurationGroupTranslationBasicStruct $configurationGroupTranslation) use ($id) {
            return $configurationGroupTranslation->getConfigurationGroupId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function(ConfigurationGroupTranslationBasicStruct $configurationGroupTranslation) {
            return $configurationGroupTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): ConfigurationGroupTranslationBasicCollection
    {
        return $this->filter(function(ConfigurationGroupTranslationBasicStruct $configurationGroupTranslation) use ($id) {
            return $configurationGroupTranslation->getLanguageId() === $id;
        });
    }

    public function getLanguageVersionIds(): array
    {
        return $this->fmap(function(ConfigurationGroupTranslationBasicStruct $configurationGroupTranslation) {
            return $configurationGroupTranslation->getLanguageVersionId();
        });
    }

    public function filterByLanguageVersionId(string $id): ConfigurationGroupTranslationBasicCollection
    {
        return $this->filter(function(ConfigurationGroupTranslationBasicStruct $configurationGroupTranslation) use ($id) {
            return $configurationGroupTranslation->getLanguageVersionId() === $id;
        });
    }

    public function getVersionIds(): array
    {
        return $this->fmap(function(ConfigurationGroupTranslationBasicStruct $configurationGroupTranslation) {
            return $configurationGroupTranslation->getVersionId();
        });
    }

    public function filterByVersionId(string $id): ConfigurationGroupTranslationBasicCollection
    {
        return $this->filter(function(ConfigurationGroupTranslationBasicStruct $configurationGroupTranslation) use ($id) {
            return $configurationGroupTranslation->getVersionId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupTranslationBasicStruct::class;
    }
}