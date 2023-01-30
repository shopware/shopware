<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1612980753AddLinkSettingsToCategory;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1612980753AddLinkSettingsToCategory
 */
class Migration1612980753AddLinkSettingsToCategoryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->rollback();
    }

    public function testMigration(): void
    {
        $context = Context::createDefaultContext();
        $uuid = Uuid::randomBytes();
        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $languageId = Uuid::fromHexToBytes($context->getLanguageId());
        $date = date('Y-m-d H:i:s');

        $this->connection->insert(
            'category',
            [
                'id' => $uuid,
                'type' => 'link',
                'version_id' => $versionId,
                'created_at' => $date,
            ]
        );

        $this->connection->insert(
            'category_translation',
            [
                'category_id' => $uuid,
                'language_id' => $languageId,
                'name' => 'My category',
                'external_link' => 'www.link.de',
                'category_version_id' => $versionId,
                'created_at' => $date,
            ]
        );

        $migration = new Migration1612980753AddLinkSettingsToCategory();
        $migration->update($this->connection);

        $schemaManager = $this->connection->getSchemaManager();
        $columns = $schemaManager->listTableColumns(CategoryTranslationDefinition::ENTITY_NAME);

        static::assertArrayHasKey('link_type', $columns);
        static::assertArrayHasKey('link_type', $columns);
        static::assertArrayHasKey('internal_link', $columns);

        $linkType = $this->connection->fetchFirstColumn('SELECT link_type FROM `category_translation` WHERE category_id = :id', ['id' => $uuid]);

        static::assertSame(CategoryDefinition::LINK_TYPE_EXTERNAL, $linkType[0]);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('
            ALTER TABLE `category_translation`
            DROP COLUMN link_type,
            DROP COLUMN link_new_tab,
            DROP COLUMN internal_link;
        ');
    }
}
