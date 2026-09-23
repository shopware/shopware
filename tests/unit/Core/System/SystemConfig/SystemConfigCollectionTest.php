<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemConfigCollection::class)]
class SystemConfigCollectionTest extends TestCase
{
    public function testFieldNameInCollection(): void
    {
        $entity = new SystemConfigEntity();
        $entity->setUniqueIdentifier('config');
        $entity->setConfigurationKey('core.basicInformation.shopName');

        $collection = new SystemConfigCollection([$entity]);

        static::assertTrue($collection->fieldNameInCollection('core.basicInformation.shopName'));
        static::assertFalse($collection->fieldNameInCollection('core.basicInformation.email'));
    }

    public function testGetApiAlias(): void
    {
        static::assertSame('system_config_collection', (new SystemConfigCollection())->getApiAlias());
    }
}
