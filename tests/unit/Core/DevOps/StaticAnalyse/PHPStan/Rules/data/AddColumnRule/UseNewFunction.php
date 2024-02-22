<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\AddColumnRule;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class UseNewFunction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 001;
    }

    public function update(Connection $connection): void
    {
        $this->swAddColumn(
            connection: $connection,
            table: 'bar',
            column: 'foo',
            type: 'VARCHAR(255)'
        );
    }
}
