<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\AddColumnRule;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class OtherAddStatements extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 001;
    }

    public function update(Connection $connection): void
    {
        // add index foo(bar)
        $connection->executeStatement('ALTER TABLE `foo` ADD INDEX `bar` (`bar`)');

        // add unique index foo(bar)
        $connection->executeStatement('ALTER TABLE `foo` ADD UNIQUE INDEX `bar` (`bar`)');

        // add foreign key foo(bar)
        $connection->executeStatement('ALTER TABLE `foo` ADD FOREIGN KEY (`bar`) REFERENCES `bar` (`id`)');

        // add constraint
        $connection->executeStatement('ALTER TABLE `foo` ADD CONSTRAINT `bar` FOREIGN KEY (`bar`) REFERENCES `bar` (`id`)');
    }
}
