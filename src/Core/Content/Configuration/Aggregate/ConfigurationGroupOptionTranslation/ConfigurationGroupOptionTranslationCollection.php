<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                           add(ConfigurationGroupOptionTranslationEntity $entity)
 * @method void                                           set(string $key, ConfigurationGroupOptionTranslationEntity $entity)
 * @method ConfigurationGroupOptionTranslationEntity[]    getIterator()
 * @method ConfigurationGroupOptionTranslationEntity[]    getElements()
 * @method ConfigurationGroupOptionTranslationEntity|null get(string $key)
 * @method ConfigurationGroupOptionTranslationEntity|null first()
 * @method ConfigurationGroupOptionTranslationEntity|null last()
 */
class ConfigurationGroupOptionTranslationCollection extends EntityCollection
{
    public function getConfigurationGroupOptionIds(): array
    {
        return $this->fmap(function (ConfigurationGroupOptionTranslationEntity $configurationGroupOptionTranslation) {
            return $configurationGroupOptionTranslation->getConfigurationGroupOptionId();
        });
    }

    public function filterByConfigurationGroupOptionId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupOptionTranslationEntity $configurationGroupOptionTranslation) use ($id) {
            return $configurationGroupOptionTranslation->getConfigurationGroupOptionId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ConfigurationGroupOptionTranslationEntity $configurationGroupOptionTranslation) {
            return $configurationGroupOptionTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupOptionTranslationEntity $configurationGroupOptionTranslation) use ($id) {
            return $configurationGroupOptionTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupOptionTranslationEntity::class;
    }
}
