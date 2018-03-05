<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionBasicStruct;


class ConfigurationGroupOptionBasicCollection extends EntityCollection
{
    /**
     * @var ConfigurationGroupOptionBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigurationGroupOptionBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigurationGroupOptionBasicStruct
    {
        return parent::current();
    }


    public function getVersionIds(): array
    {
        return $this->fmap(function(ConfigurationGroupOptionBasicStruct $configurationGroupOption) {
            return $configurationGroupOption->getVersionId();
        });
    }

    public function filterByVersionId(string $id): ConfigurationGroupOptionBasicCollection
    {
        return $this->filter(function(ConfigurationGroupOptionBasicStruct $configurationGroupOption) use ($id) {
            return $configurationGroupOption->getVersionId() === $id;
        });
    }

    public function getConfigurationGroupIds(): array
    {
        return $this->fmap(function(ConfigurationGroupOptionBasicStruct $configurationGroupOption) {
            return $configurationGroupOption->getConfigurationGroupId();
        });
    }

    public function filterByConfigurationGroupId(string $id): ConfigurationGroupOptionBasicCollection
    {
        return $this->filter(function(ConfigurationGroupOptionBasicStruct $configurationGroupOption) use ($id) {
            return $configurationGroupOption->getConfigurationGroupId() === $id;
        });
    }

    public function getConfigurationGroupVersionIds(): array
    {
        return $this->fmap(function(ConfigurationGroupOptionBasicStruct $configurationGroupOption) {
            return $configurationGroupOption->getConfigurationGroupVersionId();
        });
    }

    public function filterByConfigurationGroupVersionId(string $id): ConfigurationGroupOptionBasicCollection
    {
        return $this->filter(function(ConfigurationGroupOptionBasicStruct $configurationGroupOption) use ($id) {
            return $configurationGroupOption->getConfigurationGroupVersionId() === $id;
        });
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function(ConfigurationGroupOptionBasicStruct $configurationGroupOption) {
            return $configurationGroupOption->getMediaId();
        });
    }

    public function filterByMediaId(string $id): ConfigurationGroupOptionBasicCollection
    {
        return $this->filter(function(ConfigurationGroupOptionBasicStruct $configurationGroupOption) use ($id) {
            return $configurationGroupOption->getMediaId() === $id;
        });
    }

    public function getMediaVersionIds(): array
    {
        return $this->fmap(function(ConfigurationGroupOptionBasicStruct $configurationGroupOption) {
            return $configurationGroupOption->getMediaVersionId();
        });
    }

    public function filterByMediaVersionId(string $id): ConfigurationGroupOptionBasicCollection
    {
        return $this->filter(function(ConfigurationGroupOptionBasicStruct $configurationGroupOption) use ($id) {
            return $configurationGroupOption->getMediaVersionId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupOptionBasicStruct::class;
    }
}