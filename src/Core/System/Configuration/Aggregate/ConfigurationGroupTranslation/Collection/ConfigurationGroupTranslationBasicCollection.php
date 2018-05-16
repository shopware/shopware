<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupTranslation\Collection;

use Shopware\System\Configuration\Aggregate\ConfigurationGroupTranslation\Struct\ConfigurationGroupTranslationBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

class ConfigurationGroupTranslationBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\System\Configuration\Aggregate\ConfigurationGroupTranslation\Struct\ConfigurationGroupTranslationBasicStruct[]
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
        return $this->fmap(function (ConfigurationGroupTranslationBasicStruct $configurationGroupTranslation) {
            return $configurationGroupTranslation->getConfigurationGroupId();
        });
    }

    public function filterByConfigurationGroupId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupTranslationBasicStruct $configurationGroupTranslation) use ($id) {
            return $configurationGroupTranslation->getConfigurationGroupId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ConfigurationGroupTranslationBasicStruct $configurationGroupTranslation) {
            return $configurationGroupTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupTranslationBasicStruct $configurationGroupTranslation) use ($id) {
            return $configurationGroupTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupTranslationBasicStruct::class;
    }
}
