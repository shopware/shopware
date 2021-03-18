<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1610448012LandingPage;

class Migration1610448012LandingPageTest extends TestCase
{
    use KernelTestBehaviour;

    public function testNoLandingPageTable(): void
    {
        $conn = $this->getContainer()->get(Connection::class);
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
