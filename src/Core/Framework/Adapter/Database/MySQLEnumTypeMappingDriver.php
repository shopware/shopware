<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class MySQLEnumTypeMappingDriver extends AbstractDriverMiddleware
{
    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->registerEnumTypeMapping(parent::getDatabasePlatform());
    }

    public function createDatabasePlatformForVersion($version): AbstractPlatform
    {
        return $this->registerEnumTypeMapping(parent::createDatabasePlatformForVersion($version));
    }

    private function registerEnumTypeMapping(AbstractPlatform $platform): AbstractPlatform
    {
        $platform->registerDoctrineTypeMapping('enum', 'string');

        return $platform;
    }
}
