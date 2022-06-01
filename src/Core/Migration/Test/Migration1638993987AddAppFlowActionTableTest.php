<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1638993987AddAppFlowActionTable;

/**
 * @internal
 */
class Migration1638993987AddAppFlowActionTableTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->connection->rollBack();

        $migration = new Migration1638993987AddAppFlowActionTable();
        $migration->update($this->connection);

        $this->connection->beginTransaction();
    }

    public function testTablesArePresent(): void
    {
        $appFlowActionColumns = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM app_flow_action'), 'Field');

        static::assertContains('id', $appFlowActionColumns);
        static::assertContains('app_id', $appFlowActionColumns);
        static::assertContains('name', $appFlowActionColumns);
        static::assertContains('badge', $appFlowActionColumns);
        static::assertContains('url', $appFlowActionColumns);
        static::assertContains('parameters', $appFlowActionColumns);
        static::assertContains('config', $appFlowActionColumns);
        static::assertContains('headers', $appFlowActionColumns);
        static::assertContains('requirements', $appFlowActionColumns);
        static::assertContains('icon', $appFlowActionColumns);
        static::assertContains('sw_icon', $appFlowActionColumns);
        static::assertContains('created_at', $appFlowActionColumns);
        static::assertContains('updated_at', $appFlowActionColumns);

        $appFlowActionTranslationColumns = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM app_flow_action_translation'), 'Field');

        static::assertContains('app_flow_action_id', $appFlowActionTranslationColumns);
        static::assertContains('language_id', $appFlowActionTranslationColumns);
        static::assertContains('label', $appFlowActionTranslationColumns);
        static::assertContains('description', $appFlowActionTranslationColumns);
        static::assertContains('headline', $appFlowActionTranslationColumns);
        static::assertContains('custom_fields', $appFlowActionTranslationColumns);
        static::assertContains('created_at', $appFlowActionTranslationColumns);
        static::assertContains('updated_at', $appFlowActionTranslationColumns);
    }
}
