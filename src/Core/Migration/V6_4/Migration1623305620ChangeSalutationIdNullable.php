<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1623305620ChangeSalutationIdNullable extends MigrationStep
{
    final public const TABLES = [
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
        $sql = array_map(static fn (string $table): string => sprintf(self::TEMPLATE, $table), self::TABLES);

        $connection->executeStatement(implode('', $sql));
    }

    public function updateDestructive(Connection $connection): void
    {
        // Not needed at the moment.
    }
}
