<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1556805716NewsletterSubscriberSalutationIdDefault extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556805716;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `newsletter_receiver` MODIFY `salutation_id` BINARY(16) DEFAULT NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
