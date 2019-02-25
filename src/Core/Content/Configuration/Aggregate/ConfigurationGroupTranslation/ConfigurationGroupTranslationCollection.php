<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                     add(ConfigurationGroupTranslationEntity $entity)
 * @method void                                     set(string $key, ConfigurationGroupTranslationEntity $entity)
 * @method ConfigurationGroupTranslationEntity[]    getIterator()
 * @method ConfigurationGroupTranslationEntity[]    getElements()
 * @method ConfigurationGroupTranslationEntity|null get(string $key)
 * @method ConfigurationGroupTranslationEntity|null first()
 * @method ConfigurationGroupTranslationEntity|null last()
 */
class ConfigurationGroupTranslationCollection extends EntityCollection
{
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
