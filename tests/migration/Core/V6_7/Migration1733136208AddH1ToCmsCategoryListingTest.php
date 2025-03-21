<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1733136208AddH1ToCmsCategoryListing;

/**
 * @internal
 */
#[CoversClass(Migration1733136208AddH1ToCmsCategoryListing::class)]
class Migration1733136208AddH1ToCmsCategoryListingTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testMigrationAddsH1ToDefaultListingLayout(): void
    {
        $this->rollback();
        $this->migrate();
        $this->migrate();

        $sectionId = $this->getSectionId('Default listing layout');
        static::assertNotNull($sectionId);

        $block = $this->getBlock($sectionId);
        static::assertNotNull($block);
        static::assertSame('Category name', $block['name']);
    }

    public function testMigrationAddsH1ToDefaultListingSidebarLayout(): void
    {
        $this->rollback();
        $this->migrate();
        $this->migrate();

        $sectionId = $this->getSectionId('Default listing layout with sidebar');
        static::assertNotNull($sectionId);

        $block = $this->getBlock($sectionId);
        static::assertNotNull($block);
        static::assertSame('Category name', $block['name']);
    }

    private function migrate(): void
    {
        (new Migration1733136208AddH1ToCmsCategoryListing())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DELETE FROM cms_block WHERE name = "Category name"');
    }

    private function getSectionId(string $layoutName): ?string
    {
        return $this->connection->fetchOne(
            'SELECT cms_section.id
             FROM cms_section
             INNER JOIN cms_page_translation ON cms_page_translation.cms_page_id = cms_section.cms_page_id
             WHERE cms_page_translation.name = :name',
            ['name' => $layoutName]
        ) ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getBlock(string $sectionId): ?array
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM cms_block WHERE cms_section_id = :sectionId AND name = "Category name"',
            ['sectionId' => $sectionId]
        ) ?: null;
    }
}
