<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\AclValidPermissionsHelper;

/**
 * @internal
 */
#[CoversClass(AclValidPermissionsHelper::class)]
class AclValidPermissionsHelperTest extends TestCase
{
    public function testAclKeyValid(): void
    {
        $aclHelper = new AclValidPermissionsHelper(__DIR__ . '/data/AclValidPermissionsRule/entity-schema.json');

        // acl_role
        static::assertTrue($aclHelper->aclKeyValid('acl_role:read'));
        static::assertTrue($aclHelper->aclKeyValid('acl_role:create'));
        static::assertTrue($aclHelper->aclKeyValid('acl_role:update'));
        static::assertTrue($aclHelper->aclKeyValid('acl_role:delete'));
        static::assertFalse($aclHelper->aclKeyValid('acl_role:insert'));

        // acl_user
        static::assertTrue($aclHelper->aclKeyValid('acl_user_role:create'));

        // order
        static::assertTrue($aclHelper->aclKeyValid('order:read'));
        static::assertTrue($aclHelper->aclKeyValid('order:create'));
        static::assertTrue($aclHelper->aclKeyValid('order:update'));
        static::assertTrue($aclHelper->aclKeyValid('order:delete'));
        static::assertFalse($aclHelper->aclKeyValid('order:insert'));

        // custom permissions
        static::assertTrue($aclHelper->aclKeyValid('api_action_access-key_integration'));
        static::assertTrue($aclHelper->aclKeyValid('app'));
        static::assertFalse($aclHelper->aclKeyValid('you-dont-know-me'));
    }

    /**
     * @return array<array<string>>
     */
    public static function getInvalidSchemas(): array
    {
        return [
            'missing file' => [__DIR__ . '/data/AclValidPermissionsRule/non-existing-schema.json'],
            'scalar json' => [__DIR__ . '/data/AclValidPermissionsRule/entity-schema-value.json'],
            'invalid json' => [__DIR__ . '/data/AclValidPermissionsRule/entity-schema-invalid.json'],
        ];
    }

    #[DataProvider('getInvalidSchemas')]
    public function testConstructorWithWrongSchema(string $path): void
    {
        // missing file
        static::expectException(\RuntimeException::class);
        new AclValidPermissionsHelper($path);
    }
}
