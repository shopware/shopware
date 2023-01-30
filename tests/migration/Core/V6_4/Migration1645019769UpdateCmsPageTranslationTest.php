<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1645019769UpdateCmsPageTranslation;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1645019769UpdateCmsPageTranslation
 */
class Migration1645019769UpdateCmsPageTranslationTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdate(): void
    {
        $migration = new Migration1645019769UpdateCmsPageTranslation();
        $migration->update($this->connection);

        $cmsPageTranslations = $this->fetchLockedCmsPageTranslationsCountByName('Default listing layout');
        static::assertSame(1, $cmsPageTranslations);

        $cmsPageTranslations = $this->fetchLockedCmsPageTranslationsCountByName('Default listing layout with sidebar');
        static::assertSame(1, $cmsPageTranslations);
    }

    private function fetchLockedCmsPageTranslationsCountByName(string $cmsPageTranslationName): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(`cms_page_id`)
            FROM `cms_page_translation` INNER JOIN `cms_page` ON `cms_page_translation`.`cms_page_id` = `cms_page`.`id`
            WHERE `name` = :cmsPageTranslationName AND `locked` = 1 AND `cms_page_translation`.`updated_at` IS NULL',
            [
                'cmsPageTranslationName' => $cmsPageTranslationName,
            ]
        );
    }
}
