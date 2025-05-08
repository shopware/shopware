<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1739197440ChangeCmsBlockDefaultMargin;

/**
 * @internal
 */
#[CoversClass(Migration1739197440ChangeCmsBlockDefaultMargin::class)]
class Migration1739197440ChangeCmsBlockDefaultMarginTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @throws Exception
     */
    public function testChangeCmsBlockDefaultMargin(): void
    {
        $migration = new Migration1739197440ChangeCmsBlockDefaultMargin();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $this->checkLayout($this->connection, 'Default listing layout');
        $this->checkLayout($this->connection, 'Default listing layout with sidebar');
        $this->checkLayout($this->connection, 'Default shop page layout with contact form');
        $this->checkLayout($this->connection, 'Default shop page layout with newsletter form');
    }

    /**
     * Validates the blocks of the CMS page with the given translated name.
     *
     * @param string $layoutName - The translated name of the CMS page layout.
     *
     * @throws Exception
     */
    private function checkLayout(Connection $connection, string $layoutName): void
    {
        $layoutId = $this->findDefaultLayoutId($connection, $layoutName);
        static::assertNotNull($layoutId);

        $defaultListingBlocks = $this->getCmsPageBlocks($this->connection, $layoutId);
        static::assertNotEmpty($defaultListingBlocks);

        foreach ($defaultListingBlocks as $block) {
            static::assertNull($block['margin_left']);
            static::assertNull($block['margin_right']);
        }
    }

    /**
     * @throws Exception
     */
    private function findDefaultLayoutId(Connection $connection, string $name): ?string
    {
        $result = $connection->fetchOne(
            'SELECT cms_page_id
            FROM cms_page_translation
            INNER JOIN cms_page ON cms_page.id = cms_page_translation.cms_page_id
            WHERE cms_page.locked
            AND name = :name',
            ['name' => $name]
        );

        return $result ?: null;
    }

    /**
     * @throws Exception
     *
     * @return list<array<string, mixed>>
     */
    private function getCmsPageBlocks(Connection $connection, string $cmsPageId): array
    {
        $sectionIds = $connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id))
            FROM cms_section
            WHERE cms_page_id = :cms_page_id',
            ['cms_page_id' => $cmsPageId]
        );

        return $connection->fetchAllAssociative(
            'SELECT id, margin_left, margin_right
            FROM cms_block
            WHERE cms_section_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($sectionIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
