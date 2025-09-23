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

        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM `rule_condition`
            WHERE `value` LIKE '%+00:00%'
        SQL;

        $result = (int) $connection->fetchOne($sql);

        static::assertSame(0, $result);
    }

    public function revertMigration(Connection $connection): void
    {
        $ruleId = Uuid::randomBytes();

        $ruleStatement = <<<'SQL'
            INSERT INTO `rule` (id, name, priority, created_at)
            VALUES (:id, 'Test Rule', 100, NOW())
        SQL;

        $connection->executeStatement($ruleStatement, ['id' => $ruleId]);

        $ruleConditionId = Uuid::randomBytes();

        $ruleConditionStatement = <<<'SQL'
            INSERT INTO `rule_condition` (id, type, rule_id, value, created_at)
            VALUES (:id, 'dateRange', :ruleId, '{"fromDate": "2025-01-01T00:00:00+00:00", "toDate": "2025-12-31T23:59:59+00:00"}', NOW())
        SQL;

        $connection->executeStatement($ruleConditionStatement, ['id' => $ruleConditionId, 'ruleId' => $ruleId]);
    }
}
