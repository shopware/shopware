<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552897781AddUniqueKeyToUserMail extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552897781;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `user`
            ADD CONSTRAINT `uniq.email` UNIQUE (`email`);'
        );
    }
}
