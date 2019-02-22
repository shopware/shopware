<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                          add(ConfigurationGroupEntity $entity)
 * @method void                          set(string $key, ConfigurationGroupEntity $entity)
 * @method ConfigurationGroupEntity[]    getIterator()
 * @method ConfigurationGroupEntity[]    getElements()
 * @method ConfigurationGroupEntity|null get(string $key)
 * @method ConfigurationGroupEntity|null first()
 * @method ConfigurationGroupEntity|null last()
 */
class ConfigurationGroupCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ConfigurationGroupEntity::class;
    }
}
