<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1733136208AddH1ToCmsCategoryListing;
use Shopware\Core\Migration\V6_7\Migration1778573083ConvertCategoryNameBlockToDedicatedElement;

/**
 * @internal
 */
#[CoversClass(Migration1778573083ConvertCategoryNameBlockToDedicatedElement::class)]
class Migration1778573083ConvertCategoryNameBlockToDedicatedElementTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM cms_block WHERE name = "Category name"');
        (new Migration1733136208AddH1ToCmsCategoryListing())->update($this->connection);
    }

    public function testConvertsUnmodifiedBlocksToCategoryNameType(): void
    {
        (new Migration1778573083ConvertCategoryNameBlockToDedicatedElement())->update($this->connection);

        $blocks = $this->connection->fetchAllAssociative(
            'SELECT id, type FROM cms_block WHERE name = "Category name"'
        );

        static::assertNotEmpty($blocks);
        foreach ($blocks as $block) {
            static::assertSame('category-heading', $block['type']);

            $slot = $this->connection->fetchAssociative(
                'SELECT slot.id, slot.type, translation.config
                FROM cms_slot slot
                INNER JOIN cms_slot_translation translation ON translation.cms_slot_id = slot.id
                WHERE slot.cms_block_id = :blockId AND translation.language_id = :languageId',
                [
                    'blockId' => $block['id'],
                    'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                ]
            );

            static::assertNotFalse($slot);
            static::assertSame('category-name', $slot['type']);

            $config = json_decode((string) $slot['config'], true, 512, \JSON_THROW_ON_ERROR);
            static::assertSame('mapped', $config['content']['source']);
            static::assertSame('category.name', $config['content']['value']);
        }
    }

    public function testLeavesModifiedBlocksUntouched(): void
    {
        $block = $this->connection->fetchAssociative(
            'SELECT id FROM cms_block WHERE name = "Category name" LIMIT 1'
        );
        static::assertIsArray($block);

        $this->connection->executeStatement(
            'UPDATE cms_slot_translation translation
            INNER JOIN cms_slot slot ON slot.id = translation.cms_slot_id
            SET translation.config = :config
            WHERE slot.cms_block_id = :blockId',
            [
                'config' => json_encode([
                    'content' => ['source' => 'static', 'value' => '<h1>Custom heading</h1>'],
                ], \JSON_THROW_ON_ERROR),
                'blockId' => $block['id'],
            ]
        );

        (new Migration1778573083ConvertCategoryNameBlockToDedicatedElement())->update($this->connection);

        $blockAfter = $this->connection->fetchAssociative(
            'SELECT type FROM cms_block WHERE id = :id',
            ['id' => $block['id']]
        );
        static::assertIsArray($blockAfter);
        static::assertSame('text', $blockAfter['type']);
    }

    public function testIsIdempotent(): void
    {
        (new Migration1778573083ConvertCategoryNameBlockToDedicatedElement())->update($this->connection);
        (new Migration1778573083ConvertCategoryNameBlockToDedicatedElement())->update($this->connection);

        $blocks = $this->connection->fetchAllAssociative(
            'SELECT type FROM cms_block WHERE name = "Category name"'
        );

        static::assertNotEmpty($blocks);
        foreach ($blocks as $block) {
            static::assertSame('category-heading', $block['type']);
        }
    }
}
