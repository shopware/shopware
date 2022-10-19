<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1656928097AddNewsletterRecipientEmailIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1656928097;
    }

    public function update(Connection $connection): void
    {
        $existingIndexes = $connection->getSchemaManager()->listTableIndexes('newsletter_recipient');
        if (isset($existingIndexes['idx.newsletter_recipient.email'])) {
            return;
        }

        $connection->executeStatement('CREATE INDEX `idx.newsletter_recipient.email` ON `newsletter_recipient` (`email`)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
