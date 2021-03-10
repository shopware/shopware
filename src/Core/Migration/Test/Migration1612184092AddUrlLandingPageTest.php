<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1612184092AddUrlLandingPage;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;

class Migration1612184092AddUrlLandingPageTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->rollback();
    }

    public function testMigration(): void
    {
        $migration = new Migration1612184092AddUrlLandingPage();
        $migration->update($this->connection);

        $schemaManager = $this->connection->getSchemaManager();
        $columns = $schemaManager->listTableColumns(LandingPageTranslationDefinition::ENTITY_NAME);

        static::assertArrayHasKey('url', $columns);

        $seoUrlTemplate = $this->connection->fetchAllAssociative(
            'SELECT id
            FROM `seo_url_template`
            WHERE `seo_url_template`.`route_name` = :routeName',
            ['routeName' => LandingPageSeoUrlRoute::ROUTE_NAME]
        );

        static::assertNotEmpty($seoUrlTemplate);
    }

    private function rollback(): void
    {
        $schemaManager = $this->connection->getSchemaManager();
        $columns = $schemaManager->listTableColumns(LandingPageTranslationDefinition::ENTITY_NAME);

        if (isset($columns['url'])) {
            $this->connection->executeStatement('
                ALTER TABLE `landing_page_translation`
                DROP COLUMN url;
            ');
        }

        $this->connection->delete(
            'seo_url_template',
            [
                'route_name' => LandingPageSeoUrlRoute::ROUTE_NAME,
                'entity_name' => LandingPageDefinition::ENTITY_NAME,
                'template' => LandingPageSeoUrlRoute::DEFAULT_TEMPLATE,
            ]
        );
    }
}
