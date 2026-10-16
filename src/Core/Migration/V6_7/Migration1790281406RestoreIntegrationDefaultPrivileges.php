<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1790281406RestoreIntegrationDefaultPrivileges extends MigrationStep
{
    private const RESTORED_PRIVILEGES = [
        'language:read',
        'locale:read',
        'log_entry:create',
        'message_queue_stats:read',
    ];

    public function getCreationTimestamp(): int
    {
        return 1790281406;
    }

    public function update(Connection $connection): void
    {
        $roles = $connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(r.id)), r.privileges
             FROM acl_role r
             WHERE r.deleted_at IS NULL
               AND EXISTS (SELECT 1 FROM integration_role ir WHERE ir.acl_role_id = r.id)
               AND NOT EXISTS (SELECT 1 FROM app a WHERE a.acl_role_id = r.id)'
        );

        $connection->transactional(static function (Connection $connection) use ($roles): void {
            foreach ($roles as $roleId => $privileges) {
                try {
                    $current = json_decode((string) $privileges, true, flags: \JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    continue;
                }

                if (!\is_array($current) || !array_is_list($current)) {
                    continue;
                }

                if (array_filter($current, static fn (mixed $privilege): bool => !\is_string($privilege)) !== []) {
                    continue;
                }

                $new = array_values(array_unique([...$current, ...self::RESTORED_PRIVILEGES]));

                if ($new === $current) {
                    continue;
                }

                $connection->update(
                    'acl_role',
                    [
                        'privileges' => json_encode($new, \JSON_THROW_ON_ERROR),
                        'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ],
                    ['id' => Uuid::fromHexToBytes((string) $roleId)],
                );
            }
        });
    }
}
