<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1645019769UpdateCmsPageTranslation;

class Migration1645019769UpdateCmsPageTranslationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testUpdate(): void
    {
        $migration = new Migration1645019769UpdateCmsPageTranslation();
        $migration->update($this->connection);

        $cmsPageTranslations = $this->fetchLockedCmsPageTranslationsByName('Default listing layout');
        static::assertCount(1, $cmsPageTranslations);

        $cmsPageTranslations = $this->fetchLockedCmsPageTranslationsByName('Default listing layout with sidebar');
        static::assertCount(1, $cmsPageTranslations);
    }

    private function fetchLockedCmsPageTranslationsByName(string $cmsPageTranslationName): array
    {
        return $this->connection->fetchAll(
            'SELECT `cms_page_id`, `cms_page_version_id`, `language_id`
            FROM `cms_page_translation` INNER JOIN `cms_page` ON `cms_page_translation`.`cms_page_id` = `cms_page`.`id`
            WHERE `name` = :cmsPageTranslationName AND `locked` = 1 AND `cms_page_translation`.`updated_at` IS NULL',
            [
                'cmsPageTranslationName' => $cmsPageTranslationName,
            ]
        );
    }
}
