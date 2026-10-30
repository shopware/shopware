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
        $owners = new IdentityRoleMap(['id' => $userRoles], ['id' => $integrationRoles], []);

        static::assertSame($userRoles, $owners->rolesFor('id', IdentityType::User));
        static::assertSame($integrationRoles, $owners->rolesFor('id', IdentityType::Integration));
    }

    public function testAnAppsIntegrationIsFoundAsAnIntegrationAndAsTheApp(): void
    {
        $roles = new AclRoles(false, ['app-role']);
        $owners = new IdentityRoleMap([], ['app-integration' => $roles], ['app-integration']);

        static::assertSame($roles, $owners->rolesFor('app-integration', IdentityType::Integration));
        static::assertSame($roles, $owners->rolesFor('app-integration', IdentityType::App));
        static::assertTrue($owners->isType('app-integration', IdentityType::App));
    }

    public function testAnIntegrationWithoutAnAppIsNotTheApp(): void
    {
        $owners = new IdentityRoleMap([], ['integration' => new AclRoles(false, ['role'])], []);

        static::assertNull($owners->rolesFor('integration', IdentityType::App));
        static::assertFalse($owners->isType('integration', IdentityType::App));
    }

    public function testAnUnknownIdHasNoRoles(): void
    {
        $owners = new IdentityRoleMap([], [], []);

        static::assertNull($owners->rolesFor('unknown', IdentityType::User));
        static::assertNull($owners->rolesFor('unknown', IdentityType::Integration));
        static::assertFalse($owners->isType('unknown', IdentityType::User));
    }
}
