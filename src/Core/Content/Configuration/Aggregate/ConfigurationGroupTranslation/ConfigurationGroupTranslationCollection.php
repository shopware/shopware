<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ConfigurationGroupTranslationCollection extends EntityCollection
{
    /**
     * @var ConfigurationGroupTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigurationGroupTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): ConfigurationGroupTranslationEntity
    {
        return parent::current();
    }

    public function getConfigurationGroupIds(): array
    {
        return $this->fmap(function (ConfigurationGroupTranslationEntity $configurationGroupTranslation) {
            return $configurationGroupTranslation->getConfigurationGroupId();
        });
    }

    public function filterByConfigurationGroupId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupTranslationEntity $configurationGroupTranslation) use ($id) {
            return $configurationGroupTranslation->getConfigurationGroupId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ConfigurationGroupTranslationEntity $configurationGroupTranslation) {
            return $configurationGroupTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupTranslationEntity $configurationGroupTranslation) use ($id) {
            return $configurationGroupTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupTranslationEntity::class;
    }
}
