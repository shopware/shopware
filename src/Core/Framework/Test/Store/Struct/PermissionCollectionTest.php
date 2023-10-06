<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Store\Struct\PermissionCollection;

/**
 * @internal
 */
class PermissionCollectionTest extends TestCase
{
    private const PERMISSIONS = [
        [
            'entity' => 'product',
            'operation' => AclRoleDefinition::PRIVILEGE_CREATE,
        ],
        [
            'entity' => 'promotion',
            'operation' => AclRoleDefinition::PRIVILEGE_UPDATE,
        ],
        [
            'entity' => 'xxx',
            'operation' => AclRoleDefinition::PRIVILEGE_DELETE,
        ],
        [
            'entity' => 'plugin',
            'operation' => AclRoleDefinition::PRIVILEGE_READ,
        ],
    ];

    public function testItCategorizesAPermissionCollection(): void
    {
        $permissionCollection = new PermissionCollection(self::PERMISSIONS);
        $categorizedCollection = $permissionCollection->getCategorizedPermissions();

        static::assertCount(3, $categorizedCollection);
        static::assertEquals([
            'product',
            'promotion',
            'other',
        ], array_keys($categorizedCollection));
    }

    public function testItAddsMissingReadPermissionDependencies(): void
    {
        $permissionCollection = new PermissionCollection(self::PERMISSIONS);

        static::assertNotEmpty($permissionCollection->getElements());
        static::assertCount(7, $permissionCollection->getElements());

        $countReadPermissions = 0;
        foreach ($permissionCollection->getElements() as $permission) {
            if ($permission->getOperation() === AclRoleDefinition::PRIVILEGE_READ) {
                ++$countReadPermissions;
            }
        }

        static::assertEquals(4, $countReadPermissions);
    }

    public function testItFiltersDuplicatePermissions(): void
    {
        $permissionsData = [
            [
                'entity' => 'product',
                'operation' => AclRoleDefinition::PRIVILEGE_DELETE,
            ],
            [
                'entity' => 'product',
                'operation' => AclRoleDefinition::PRIVILEGE_DELETE,
            ],
        ];

        $permissionCollection = new PermissionCollection($permissionsData);

        static::assertCount(2, $permissionCollection->getElements());

        static::assertEquals('product', $permissionCollection->getElements()[0]->getEntity());
        static::assertEquals('delete', $permissionCollection->getElements()[0]->getOperation());

        static::assertEquals('product', $permissionCollection->getElements()[1]->getEntity());
        static::assertEquals('read', $permissionCollection->getElements()[1]->getOperation());
    }
}
