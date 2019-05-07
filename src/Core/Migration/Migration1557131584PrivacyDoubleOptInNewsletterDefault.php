<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1557131584PrivacyDoubleOptInNewsletterDefault extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1557131584;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.privacy.doiNewsletter',
            'configuration_value' => '{"_value": true}',
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]);
    }
}
