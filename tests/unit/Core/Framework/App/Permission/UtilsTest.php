<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Permission;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Privileges\Utils;
use Shopware\Core\Framework\Store\Struct\PermissionCollection;
use Shopware\Core\Framework\Store\Struct\PermissionStruct;

/**
 * @internal
 */
#[CoversClass(Utils::class)]
class UtilsTest extends TestCase
{
    public function testMakePermissions(): void
    {
        $permissions = [
            'product:create',
            'product:update',
            'product:delete',
            'category:delete',
            'product_manufacturer:create',
            'product_manufacturer:delete',
            'tax:create',
            'language:read',
            'custom_field_set:update',
            'order:read',
            'user_change_me:permission',
        ];

        $result = Utils::makePermissions($permissions);

        static::assertSame(
            [
                ['entity' => 'product', 'operation' => 'create'],
                ['entity' => 'product', 'operation' => 'update'],
                ['entity' => 'product', 'operation' => 'delete'],
                ['entity' => 'category', 'operation' => 'delete'],
                ['entity' => 'product_manufacturer', 'operation' => 'create'],
                ['entity' => 'product_manufacturer', 'operation' => 'delete'],
                ['entity' => 'tax', 'operation' => 'create'],
                ['entity' => 'language', 'operation' => 'read'],
                ['entity' => 'custom_field_set', 'operation' => 'update'],
                ['entity' => 'order', 'operation' => 'read'],
                ['entity' => 'additional_privileges', 'operation' => 'user_change_me:permission'],
            ],
            $result
        );
    }

    public function testMakeCategorized(): void
    {
        $permissions = [
            'product:create',
            'product:update',
            'product:delete',
            'category:delete',
            'product_manufacturer:create',
            'product_manufacturer:delete',
            'tax:create',
            'language:read',
            'custom_field_set:update',
            'order:read',
            'user_change_me:permission',
        ];

        $result = Utils::makeCategorizedPermissions($permissions);

        static::assertCount(6, $result);
        static::assertSame(
            ['category', 'custom_fields', 'order', 'product', 'settings', 'additional_privileges'],
            array_keys($result)
        );

        static::assertInstanceOf(PermissionCollection::class, $result['category']);
        static::assertInstanceOf(PermissionCollection::class, $result['custom_fields']);
        static::assertInstanceOf(PermissionCollection::class, $result['order']);
        static::assertInstanceOf(PermissionCollection::class, $result['product']);
        static::assertInstanceOf(PermissionCollection::class, $result['settings']);
        static::assertInstanceOf(PermissionCollection::class, $result['additional_privileges']);

        $mapper = fn (PermissionStruct $p) => ['entity' => $p->getEntity(), 'op' => $p->getOperation()];

        static::assertCount(1, $result['category']->getElements());
        static::assertSame(
            [['entity' => 'category', 'op' => 'delete']],
            $result['category']->map($mapper)
        );

        static::assertCount(1, $result['custom_fields']->getElements());
        static::assertSame(
            [['entity' => 'custom_field_set', 'op' => 'update']],
            $result['custom_fields']->map($mapper)
        );

        static::assertCount(1, $result['order']->getElements());
        static::assertSame(
            [['entity' => 'order', 'op' => 'read']],
            $result['order']->map($mapper)
        );

        static::assertCount(5, $result['product']->getElements());
        static::assertSame(
            [
                ['entity' => 'product', 'op' => 'create'],
                ['entity' => 'product', 'op' => 'update'],
                ['entity' => 'product', 'op' => 'delete'],
                ['entity' => 'product_manufacturer', 'op' => 'create'],
                ['entity' => 'product_manufacturer', 'op' => 'delete'],
            ],
            $result['product']->map($mapper)
        );

        static::assertCount(2, $result['settings']->getElements());
        static::assertSame(
            [
                ['entity' => 'tax', 'op' => 'create'],
                ['entity' => 'language', 'op' => 'read'],
            ],
            $result['settings']->map($mapper)
        );

        static::assertCount(1, $result['additional_privileges']->getElements());
        static::assertSame(
            [['entity' => 'additional_privileges', 'op' => 'user_change_me:permission']],
            $result['additional_privileges']->map($mapper)
        );
    }
}
