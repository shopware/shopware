<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * Tells every Admin user once to set the new shop owner's country
 */
#[Package('checkout')]
class Migration1792476248AddSellerCountryNotification extends MigrationStep
{
    public const NOTIFICATION_ID = '1f58bb5a97d74a6c9857aca72880e153';

    public function getCreationTimestamp(): int
    {
        return 1792476248;
    }

    public function update(Connection $connection): void
    {
        // Fresh installations create their users afterwards, and users only see notifications created after them
        if ($this->isInstallation()) {
            return;
        }

        $connection->executeStatement(
            <<<'SQL'
            INSERT IGNORE INTO `notification` (`id`, `status`, `message`, `admin_only`, `required_privileges`, `created_at`)
            VALUES (:id, :status, :message, 0, :requiredPrivileges, :createdAt)
            SQL,
            [
                'id' => Uuid::fromHexToBytes(self::NOTIFICATION_ID),
                'status' => 'warning',
                'message' => 'sw-settings-basic-information.sellerCountryNotification.message',
                'requiredPrivileges' => json_encode(['system.system_config'], \JSON_THROW_ON_ERROR),
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }
}
