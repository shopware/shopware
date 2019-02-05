<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SystemConfigCollection extends EntityCollection
{
    public function fieldNameInCollection(string $fieldName): bool
    {
        /** @var SystemConfigEntity $systemConfigEntity */
        foreach ($this->getElements() as $systemConfigEntity) {
            if ($systemConfigEntity->getConfigurationKey() === $fieldName) {
                return true;
            }
        }

        return false;
    }

    protected function getExpectedClass(): string
    {
        return SystemConfigEntity::class;
    }
}
