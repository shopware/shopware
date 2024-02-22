<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\AddColumnRule;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class UsePlainSqlJustAdd extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `bar` ADD `foo` VARCHAR(255);');
    }
}
