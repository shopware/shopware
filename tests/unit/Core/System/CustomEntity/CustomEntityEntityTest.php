<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\CustomEntityEntity;

/**
 * @internal
 */
#[CoversClass(CustomEntityEntity::class)]
class CustomEntityEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $entity = new CustomEntityEntity();

        $entity->setId('123');
        static::assertEquals('123', $entity->getId());

        $entity->setName('Test Entity');
        static::assertEquals('Test Entity', $entity->getName());

        $entity->setStoreApiAware(true);
        static::assertTrue($entity->getStoreApiAware());

        $entity->setAppId('app-123');
        static::assertEquals('app-123', $entity->getAppId());

        $entity->setPluginId('plugin-123');
        static::assertEquals('plugin-123', $entity->getPluginId());

        $fields = [
            [
                'name' => 'field1',
            ],
            [
                'name' => 'field2',
            ],
        ];

        $entity->setFields($fields);
        static::assertEquals($fields, $entity->getFields());

        $flags = [];

        $entity->setFlags($flags);
        static::assertEquals($flags, $entity->getFlags());

        $entity->setCustomFieldsAware(true);
        static::assertTrue($entity->getCustomFieldsAware());

        $entity->setLabelProperty('name');
        static::assertEquals('name', $entity->getLabelProperty());
    }
}
