<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1794988092UpdateShopwarePaymentsRolePrivileges extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'sw-payments.viewer' => [
            'payment.viewer',
        ],
    ];

    /**
     * These roles granted nothing beyond the viewer before they depended on `payment.editor`.
     * Removing them makes an admin tick them again, which visibly ticks `payment.editor` too.
     * Saving a stored role with them would grant `payment.editor` without the grid showing it.
     */
    private const ROLES_REQUIRING_PAYMENT_EDITOR = [
        'sw-payments.editor',
        'sw-payments.creator',
    ];

    public function getCreationTimestamp(): int
    {
        return 1794988092;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);

        foreach ($connection->iterateAssociative('SELECT id, privileges FROM acl_role') as $role) {
            $privileges = json_decode((string) $role['privileges'], true, 512, \JSON_THROW_ON_ERROR);

            if (\in_array('payment.editor', $privileges, true)) {
                continue;
            }

            $updated = array_values(array_diff($privileges, self::ROLES_REQUIRING_PAYMENT_EDITOR));
            if ($updated === $privileges) {
                continue;
            }

            $connection->update('acl_role', [
                'privileges' => json_encode($updated, \JSON_THROW_ON_ERROR),
                'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], ['id' => $role['id']]);
        }
    }
}
