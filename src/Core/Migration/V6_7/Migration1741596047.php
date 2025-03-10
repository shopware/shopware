<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1741596047 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1741596047;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery("
            UPDATE rule_condition
            SET value = JSON_SET(
                value,
                '$.fromDate', DATE(JSON_UNQUOTE(JSON_EXTRACT(value, '$.fromDate'))),
                '$.toDate', DATE(JSON_UNQUOTE(JSON_EXTRACT(value, '$.toDate')))
            )
            WHERE JSON_UNQUOTE(JSON_EXTRACT(value, '$.useTime')) = 'false'
        ");

    }
}
