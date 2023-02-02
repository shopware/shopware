<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1623305620ChangeSalutationIdNullable extends MigrationStep
{
    public const TABLES = [
        'customer_address',
        'customer',
        'order_customer',
        'order_address',
    ];

    private const TEMPLATE = <<<'SQL'
ALTER TABLE `%s` MODIFY `salutation_id` binary(16) NULL;

SQL;

    public function getCreationTimestamp(): int
    {
        return 1623305620;
    }

    public function update(Connection $connection): void
    {
        $sql = array_map(static function (string $table): string {
            return sprintf(self::TEMPLATE, $table);
        }, self::TABLES);

        $connection->executeStatement(implode('', $sql));
    }

    public function updateDestructive(Connection $connection): void
    {
        // Not needed at the moment.
    }
}
