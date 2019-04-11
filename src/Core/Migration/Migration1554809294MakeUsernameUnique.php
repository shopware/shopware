<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1554809294MakeUsernameUnique extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554809294;
    }

    public function update(Connection $connection): void
    {
        $duplicates = $connection->fetchAll('
          SELECT `username`, Count(*) c
          FROM `user`
          GROUP BY `username`
          HAVING c > 1;
          '
        );

        $updateStmt = $connection->prepare('
            UPDATE `user`
            SET `username`= `email`
            WHERE `username` = :username AND `username` <> "admin"
            '
        );

        foreach ($duplicates as $duplicated) {
            $updateStmt->execute($duplicated);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `user`
            ADD UNIQUE (`username`);
            '
        );
    }
}
