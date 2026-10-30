<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Acl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\AclRoles;
use Shopware\Core\Framework\Api\Acl\IdentityRoleMap;
use Shopware\Core\Framework\Api\Acl\IdentityType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IdentityRoleMap::class)]
class IdentityRoleMapTest extends TestCase
{
    public function testAUserAndAnIntegrationWithTheSameIdKeepTheirOwnRoles(): void
    {
        $userRoles = new AclRoles(false, ['user-role']);
        $integrationRoles = new AclRoles(false, ['integration-role']);
        $owners = new IdentityRoleMap(['id' => $userRoles], ['id' => $integrationRoles]);

        static::assertSame($userRoles, $owners->rolesFor('id', IdentityType::User));
        static::assertSame($integrationRoles, $owners->rolesFor('id', IdentityType::Integration));
    }

    public function testAnAppHasNoRoles(): void
    {
        $owners = new IdentityRoleMap([], ['id' => new AclRoles(false, ['role'])]);

        static::assertNull($owners->rolesFor('id', IdentityType::App));
    }

    public function testAnUnknownIdHasNoRoles(): void
    {
        $owners = new IdentityRoleMap([], []);

        static::assertNull($owners->rolesFor('unknown', IdentityType::User));
        static::assertNull($owners->rolesFor('unknown', IdentityType::Integration));
    }
}
