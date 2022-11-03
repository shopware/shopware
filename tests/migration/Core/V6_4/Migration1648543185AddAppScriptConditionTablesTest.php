<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionDefinition;
use Shopware\Core\Framework\App\Aggregate\AppScriptConditionTranslation\AppScriptConditionTranslationDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1648543185AddAppScriptConditionTables;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_4\Migration1648543185AddAppScriptConditionTables
 */
class Migration1648543185AddAppScriptConditionTablesTest extends TestCase
{
    private Connection $connection;

    /**
     * @var AbstractSchemaManager<MySQLPlatform>
     */
    private AbstractSchemaManager $schemaManager;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->schemaManager = $this->connection->createSchemaManager();
    }

    public function testMigration(): void
    {
        $this->connection->executeStatement('ALTER TABLE `rule_condition` DROP FOREIGN KEY `fk.rule_condition.script_id`;');
        $this->connection->executeStatement('ALTER TABLE `rule_condition` DROP COLUMN `script_id`;');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_script_condition_translation`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_script_condition`');

        $migration = new Migration1648543185AddAppScriptConditionTables();
        $migration->update($this->connection);

        $columns = $this->schemaManager->listTableColumns(AppScriptConditionDefinition::ENTITY_NAME);

        static::assertArrayHasKey('id', $columns);
        static::assertArrayHasKey('app_id', $columns);
        static::assertArrayHasKey('identifier', $columns);
        static::assertArrayHasKey('active', $columns);
        static::assertArrayHasKey('`group`', $columns);
        static::assertArrayHasKey('script', $columns);
        static::assertArrayHasKey('constraints', $columns);
        static::assertArrayHasKey('config', $columns);

        $columns = $this->schemaManager->listTableColumns(AppScriptConditionTranslationDefinition::ENTITY_NAME);

        static::assertArrayHasKey('app_script_condition_id', $columns);
        static::assertArrayHasKey('language_id', $columns);
        static::assertArrayHasKey('name', $columns);

        $columns = $this->schemaManager->listTableColumns(RuleConditionDefinition::ENTITY_NAME);

        static::assertArrayHasKey('script_id', $columns);

        $foreignKeys = $this->schemaManager->listTableForeignKeys(RuleConditionDefinition::ENTITY_NAME);
        $scriptIdForeignKey = null;

        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey->getColumns() === ['script_id'] && $foreignKey->getForeignTableName() === AppScriptConditionDefinition::ENTITY_NAME) {
                $scriptIdForeignKey = $foreignKey;

                break;
            }
        }

        static::assertNotNull($scriptIdForeignKey);
        static::assertEquals('SET NULL', $scriptIdForeignKey->onDelete());
        static::assertEquals('CASCADE', $scriptIdForeignKey->onUpdate());
    }
}
