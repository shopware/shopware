<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class TestIntegration
{
    private function __construct(
        private readonly string $id,
        private readonly ?string $aclRoleId,
    ) {
    }

    /**
     * @param list<string> $permissions
     */
    public static function create(Connection $connection, array $permissions = []): TestIntegration
    {
        $id = self::insertIntegration($connection, admin: false);

        if ($permissions === []) {
            return new TestIntegration($id, null);
        }

        $roleId = Uuid::randomHex();

        $connection->insert('acl_role', [
            'id' => Uuid::fromHexToBytes($roleId),
            'name' => $roleId,
            'privileges' => json_encode($permissions, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('integration_role', [
            'integration_id' => Uuid::fromHexToBytes($id),
            'acl_role_id' => Uuid::fromHexToBytes($roleId),
        ]);

        return new TestIntegration($id, $roleId);
    }

    public static function createAdmin(Connection $connection): TestIntegration
    {
        return new TestIntegration(self::insertIntegration($connection, admin: true), null);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAclRoleId(): ?string
    {
        return $this->aclRoleId;
    }

    private static function insertIntegration(Connection $connection, bool $admin): string
    {
        $id = Uuid::randomHex();

        $connection->insert('integration', [
            'id' => Uuid::fromHexToBytes($id),
            'access_key' => AccessKeyHelper::generateAccessKey('integration'),
            'secret_access_key' => TestDefaults::HASHED_PASSWORD,
            'label' => 'test integration',
            'admin' => (int) $admin,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $id;
    }
}
