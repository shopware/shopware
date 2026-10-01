<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1758612662UpdateDateRangeRuleDateTimeFormat;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(Migration1758612662UpdateDateRangeRuleDateTimeFormat::class)]
class Migration1758612662UpdateDateRangeRuleDateTimeFormatTest extends TestCase
{
    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1758612662,
            (new Migration1758612662UpdateDateRangeRuleDateTimeFormat())->getCreationTimestamp()
        );
    }

    public function testRenameSnippetSetBaseFiles(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1758612662UpdateDateRangeRuleDateTimeFormat();

        $this->revertMigration($connection);

        $migration->update($connection);
        $migration->update($connection);

        $result = (int) $connection->createQueryBuilder()
            ->delete('rule_condition')
            ->where('value LIKE :value')
            ->setParameter('value', '%+00:00%')
            ->executeStatement();

        static::assertSame(0, $result);
    }

    public function revertMigration(Connection $connection): void
    {
        $ruleId = Uuid::randomBytes();

        $connection->createQueryBuilder()
            ->insert('rule')
            ->values([
                'id' => ':id',
                'name' => '\'Test Rule\'',
                'priority' => '100',
                'created_at' => 'NOW()',
            ])
            ->setParameter('id', $ruleId)
            ->executeStatement();

        $ruleConditionId = Uuid::randomBytes();

        $connection->createQueryBuilder()
            ->insert('rule_condition')
            ->values([
                'id' => ':id',
                'type' => '\'dateRange\'',
                'rule_id' => ':ruleId',
                'value' => '\'{"fromDate": "2025-01-01T00:00:00+00:00", "toDate": "2025-12-31T23:59:59+00:00"}\'',
                'created_at' => 'NOW()',
            ])
            ->setParameter('id', $ruleConditionId)
            ->setParameter('ruleId', $ruleId)
            ->executeStatement();
    }
}
