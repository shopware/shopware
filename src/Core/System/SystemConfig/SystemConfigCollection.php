<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                    add(SystemConfigEntity $entity)
 * @method void                    set(string $key, SystemConfigEntity $entity)
 * @method SystemConfigEntity[]    getIterator()
 * @method SystemConfigEntity[]    getElements()
 * @method SystemConfigEntity|null get(string $key)
 * @method SystemConfigEntity|null first()
 * @method SystemConfigEntity|null last()
 */
class SystemConfigCollection extends EntityCollection
{
    public function fieldNameInCollection(string $fieldName): bool
    {
        foreach ($this->getIterator() as $systemConfigEntity) {
            if ($systemConfigEntity->getConfigurationKey() === $fieldName) {
                return true;
            }
        }

        return false;
    }

    public function getApiAlias(): string
    {
        return 'system_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return SystemConfigEntity::class;
    }
}
