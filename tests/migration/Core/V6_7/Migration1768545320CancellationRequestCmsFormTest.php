<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1768545320CancellationRequestCmsForm;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1768545320CancellationRequestCmsForm::class)]
class Migration1768545320CancellationRequestCmsFormTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdate(): void
    {
        $cmsPage_byteId = $this->getCmsPageId();
        $cmsSection_byteId = $this->getCmsSectionId($cmsPage_byteId);
        $cmsBlock_byteId = $this->getCmsBlockId();

        if ($cmsPage_byteId !== null) {
            $this->connection->delete('cms_page', ['id' => $cmsPage_byteId]);
            $this->connection->delete('cms_section', ['cms_page_id' => $cmsPage_byteId]);
        }

        if ($cmsSection_byteId !== null) {
            $this->connection->delete('cms_block', ['cms_section_id' => $cmsSection_byteId]);
        }

        if ($cmsBlock_byteId !== null) {
            $this->connection->delete('cms_block', ['id' => $cmsBlock_byteId]);
            $this->connection->delete('cms_slot', ['cms_block_id' => $cmsPage_byteId]);
        }

        $migration = new Migration1768545320CancellationRequestCmsForm();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $cmsPageResult = $this->getCmsPage();
        static::assertArrayHasKey('id', $cmsPageResult);
        static::assertIsString($cmsPageResult['id']);
        static::assertArrayHasKey('type', $cmsPageResult);
        static::assertSame('page', $cmsPageResult['type']);
        static::assertArrayHasKey('translations', $cmsPageResult);
        static::assertIsArray($cmsPageResult['translations']);
        static::assertCount(2, $cmsPageResult['translations']);

        $cmsSectionResult = $this->getCmsSection($cmsPageResult['id']);
        static::assertArrayHasKey('id', $cmsSectionResult);
        static::assertIsString($cmsSectionResult['id']);
        static::assertArrayHasKey('type', $cmsSectionResult);
        static::assertSame('default', $cmsSectionResult['type']);

        $cmsBlockResult = $this->getCmsBlock($cmsSectionResult['id']);
        static::assertArrayHasKey('id', $cmsBlockResult);
        static::assertIsString($cmsBlockResult['id']);
        static::assertArrayHasKey('section_position', $cmsBlockResult);
        static::assertSame('main', $cmsBlockResult['section_position']);
        static::assertArrayHasKey('type', $cmsBlockResult);
        static::assertSame('form', $cmsBlockResult['type']);
        static::assertArrayHasKey('name', $cmsBlockResult);
        static::assertSame('Cancellation request form', $cmsBlockResult['name']);

        $cmsSlotResult = $this->getCmsSlot($cmsBlockResult['id']);
        static::assertArrayHasKey('id', $cmsSlotResult);
        static::assertIsString($cmsSlotResult['id']);
        static::assertArrayHasKey('type', $cmsSlotResult);
        static::assertSame('form', $cmsSlotResult['type']);
        static::assertArrayHasKey('slot', $cmsSlotResult);
        static::assertSame('content', $cmsSlotResult['slot']);
        static::assertArrayHasKey('translations', $cmsSlotResult);
        static::assertIsArray($cmsSlotResult['translations']);
        static::assertCount(2, $cmsSlotResult['translations']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getCmsPage(): array
    {
        $cmsPage_byteId = $this->getCmsPageId();
        static::assertIsString($cmsPage_byteId);

        $result = $this->connection->executeQuery(
            'SELECT * FROM `cms_page` WHERE `cms_page`.`id` = :id',
            ['id' => $cmsPage_byteId]
        )->fetchAssociative();
        static::assertIsArray($result);

        $translationResult = $this->connection->executeQuery(
            'SELECT * FROM `cms_page_translation` WHERE `cms_page_id` = :cmsPageId',
            ['cmsPageId' => $cmsPage_byteId]
        )->fetchAllAssociative();
        static::assertIsArray($translationResult);

        $result['translations'] = $translationResult;

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCmsSection(string $cmsPage_byteId): array
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM `cms_section` WHERE `cms_page_id` = :cmsPageId',
            ['cmsPageId' => $cmsPage_byteId]
        )->fetchAssociative();

        static::assertIsArray($result);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCmsBlock(string $cmsSection_byteId): array
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM `cms_block` WHERE `cms_section_id` = :cmsSectionId',
            ['cmsSectionId' => $cmsSection_byteId]
        )->fetchAssociative();

        static::assertIsArray($result);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCmsSlot(string $cmsBlock_byteId): array
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM `cms_slot` WHERE `cms_block_id` = :cmsBlockId',
            ['cmsBlockId' => $cmsBlock_byteId]
        )->fetchAssociative();

        static::assertIsArray($result);
        static::assertArrayHasKey('id', $result);
        static::assertIsString($result['id']);

        $cmsSlotTranslation = $this->connection->executeQuery(
            'SELECT * FROM `cms_slot_translation` WHERE `cms_slot_id` = :cmsSlotId',
            ['cmsSlotId' => $result['id']]
        )->fetchAllAssociative();

        static::assertIsArray($cmsSlotTranslation);

        $result['translations'] = $cmsSlotTranslation;

        return $result;
    }

    private function getCmsPageId(): ?string
    {
        $sql = <<<'SQL'
SELECT `id` 
FROM `cms_page` AS `page`
INNER JOIN `cms_page_translation` AS `page_translation` ON `page`.`id` = `page_translation`.`cms_page_id`
WHERE page_translation.name = :name
SQL;

        $cmsPage_byteId = $this->connection->executeQuery(
            $sql,
            ['name' => Migration1768545320CancellationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name']]
        )->fetchOne();

        if (!\is_string($cmsPage_byteId)) {
            return null;
        }

        return $cmsPage_byteId;
    }

    private function getCmsSectionId(?string $cmsPage_byteId): ?string
    {
        $cmsSection_byteId = $this->connection->executeQuery(
            'SELECT `id` FROM `cms_section` WHERE `cms_page_id` = :cmsPageId',
            ['cmsPageId' => $cmsPage_byteId]
        )->fetchOne();

        if (!Uuid::isValid(Uuid::fromBytesToHex($cmsSection_byteId))) {
            return null;
        }

        return $cmsSection_byteId;
    }

    private function getCmsBlockId(): ?string
    {
        $cmsBlock_byteId = $this->connection->executeQuery(
            'SELECT `id` FROM `cms_block` WHERE `name` = :cmsBlockName',
            ['cmsBlockName' => Migration1768545320CancellationRequestCmsForm::CMS_BLOCK_NAME]
        )->fetchOne();

        if (!Uuid::isValid(Uuid::fromBytesToHex($cmsBlock_byteId))) {
            return null;
        }

        return $cmsBlock_byteId;
    }
}
