<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1610448012LandingPage;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1610448012LandingPage
 */
class Migration1610448012LandingPageTest extends TestCase
{
    public function testNoLandingPageTable(): void
    {
        $conn = KernelLifecycleManager::getConnection();
        $conn->executeStatement('DROP TABLE `landing_page_tag`');
        $conn->executeStatement('DROP TABLE `landing_page_translation`');
        $conn->executeStatement('DROP TABLE `landing_page_sales_channel`');
        $conn->executeStatement('DROP TABLE `landing_page`');

        $migration = new Migration1610448012LandingPage();
        $migration->update($conn);
        $exists = $conn->fetchFirstColumn('SELECT COUNT(*) FROM `landing_page`') !== false;

        static::assertTrue($exists);
    }
}
