<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1768545320RevocationRequestCmsForm;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1768545320RevocationRequestCmsForm::class)]
class Migration1768545320RevocationRequestCmsFormTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1768545320, (new Migration1768545320RevocationRequestCmsForm())->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->deletePageSectionBlockAndSlot();

        $migration = new Migration1768545320RevocationRequestCmsForm();
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
        static::assertSame('Revocation request form', $cmsBlockResult['name']);

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

    public function testUpdateDoesNotReuseBlockWithSameNameFromDifferentSection(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->deletePageSectionBlockAndSlot();

            $unrelatedBlockByteId = $this->insertUnrelatedCmsBlockWithSameName();

            $migration = new Migration1768545320RevocationRequestCmsForm();
            $migration->update($this->connection);
            $migration->update($this->connection);

            $cmsPageResult = $this->getCmsPage();
            $cmsSectionResult = $this->getCmsSection($cmsPageResult['id']);
            $cmsBlockResult = $this->getCmsBlock($cmsSectionResult['id']);

            static::assertIsString($cmsBlockResult['id']);
            static::assertNotSame($unrelatedBlockByteId, $cmsBlockResult['id']);

            $cmsSlotResult = $this->getCmsSlot($cmsBlockResult['id']);
            static::assertSame($cmsBlockResult['id'], $cmsSlotResult['cms_block_id']);
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testUpdateSkipsDuplicateTranslationsWhenGermanUsesSystemLanguage(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->deletePageSectionBlockAndSlot();

            $systemLocaleByteId = $this->getLocaleIdByLanguageId(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM));

            $enLocaleByteId = $this->getLocaleIdByCode('en-GB');
            $deLocaleByteId = $this->getLocaleIdByCode('de-DE');

            static::assertIsString($enLocaleByteId);
            static::assertIsString($deLocaleByteId);

            // ensure en-GB locale is not there
            $this->updateLocaleCode($enLocaleByteId, 'old-en-GB');

            if ($systemLocaleByteId !== $deLocaleByteId) {
                // ensure de-DE locale is not there
                $this->updateLocaleCode($deLocaleByteId, 'old-de-DE');
                // ensure system locale is de-DE
                $this->updateLocaleCode($systemLocaleByteId, 'de-DE');
            }

            $migration = new Migration1768545320RevocationRequestCmsForm();
            $migration->update($this->connection);
            $migration->update($this->connection);

            $cmsPageResult = $this->getCmsPage();
            static::assertCount(1, $cmsPageResult['translations']);

            $cmsSectionResult = $this->getCmsSection($cmsPageResult['id']);
            $cmsBlockResult = $this->getCmsBlock($cmsSectionResult['id']);
            $cmsSlotResult = $this->getCmsSlot($cmsBlockResult['id']);

            static::assertCount(1, $cmsSlotResult['translations']);
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getCmsPage(): array
    {
        $cmsPageByteId = $this->getCmsPageId();
        static::assertIsString($cmsPageByteId);

        $result = $this->connection->executeQuery(
            'SELECT * FROM `cms_page` WHERE `cms_page`.`id` = :id',
            ['id' => $cmsPageByteId]
        )->fetchAssociative();
        static::assertIsArray($result);

        $translationResult = $this->connection->executeQuery(
            'SELECT * FROM `cms_page_translation` WHERE `cms_page_id` = :cmsPageId',
            ['cmsPageId' => $cmsPageByteId]
        )->fetchAllAssociative();
        static::assertIsArray($translationResult);

        $result['translations'] = $translationResult;

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCmsSection(string $cmsPageByteId): array
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM `cms_section` WHERE `cms_page_id` = :cmsPageId',
            ['cmsPageId' => $cmsPageByteId]
        )->fetchAssociative();

        static::assertIsArray($result);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCmsBlock(string $cmsSectionByteId): array
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM `cms_block` WHERE `cms_section_id` = :cmsSectionId',
            ['cmsSectionId' => $cmsSectionByteId]
        )->fetchAssociative();

        static::assertIsArray($result);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCmsSlot(string $cmsBlockByteId): array
    {
        $result = $this->connection->executeQuery(
            'SELECT * FROM `cms_slot` WHERE `cms_block_id` = :cmsBlockId',
            ['cmsBlockId' => $cmsBlockByteId]
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

        $cmsPageByteId = $this->connection->executeQuery(
            $sql,
            ['name' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name']]
        )->fetchOne();

        if (!\is_string($cmsPageByteId)) {
            return null;
        }

        return $cmsPageByteId;
    }

    private function getCmsSectionId(?string $cmsPageByteId): ?string
    {
        $cmsSectionByteId = $this->connection->executeQuery(
            'SELECT `id` FROM `cms_section` WHERE `cms_page_id` = :cmsPageId',
            ['cmsPageId' => $cmsPageByteId]
        )->fetchOne();

        if (!\is_string($cmsSectionByteId)) {
            return null;
        }

        if (!Uuid::isValid(Uuid::fromBytesToHex($cmsSectionByteId))) {
            return null;
        }

        return $cmsSectionByteId;
    }

    private function getCmsBlockId(): ?string
    {
        $cmsBlockByteId = $this->connection->executeQuery(
            'SELECT `id` FROM `cms_block` WHERE `name` = :cmsBlockName',
            ['cmsBlockName' => Migration1768545320RevocationRequestCmsForm::CMS_BLOCK_NAME]
        )->fetchOne();

        if (!\is_string($cmsBlockByteId)) {
            return null;
        }

        if (!Uuid::isValid(Uuid::fromBytesToHex($cmsBlockByteId))) {
            return null;
        }

        return $cmsBlockByteId;
    }

    private function getLocaleIdByLanguageId(string $languageByteId): string
    {
        $localeByteId = $this->connection->fetchOne(
            'SELECT `locale_id` FROM `language` WHERE `id` = :languageId',
            ['languageId' => $languageByteId]
        );

        static::assertIsString($localeByteId);

        return $localeByteId;
    }

    private function getLocaleIdByCode(string $localeCode): ?string
    {
        $localeByteId = $this->connection->fetchOne(
            'SELECT `id` FROM `locale` WHERE `code` = :code',
            ['code' => $localeCode]
        );

        if (!\is_string($localeByteId)) {
            return null;
        }

        return $localeByteId;
    }

    private function updateLocaleCode(string $localeByteId, string $localeCode): void
    {
        $this->connection->update(
            'locale',
            ['code' => $localeCode],
            ['id' => $localeByteId]
        );
    }

    private function insertUnrelatedCmsBlockWithSameName(): string
    {
        $versionByteId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $cmsPageByteId = Uuid::randomBytes();
        $cmsSectionByteId = Uuid::randomBytes();
        $cmsBlockByteId = Uuid::randomBytes();
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('cms_page', [
            'id' => $cmsPageByteId,
            'version_id' => $versionByteId,
            'type' => 'page',
            'locked' => 1,
            'created_at' => $createdAt,
        ]);

        $this->connection->insert('cms_section', [
            'id' => $cmsSectionByteId,
            'version_id' => $versionByteId,
            'cms_page_id' => $cmsPageByteId,
            'cms_page_version_id' => $versionByteId,
            'position' => 0,
            'type' => 'default',
            'created_at' => $createdAt,
        ]);

        $this->connection->insert('cms_block', [
            'id' => $cmsBlockByteId,
            'version_id' => $versionByteId,
            'created_at' => $createdAt,
            'cms_section_id' => $cmsSectionByteId,
            'cms_section_version_id' => $versionByteId,
            'locked' => 1,
            'position' => 1,
            'type' => 'form',
            'name' => Migration1768545320RevocationRequestCmsForm::CMS_BLOCK_NAME,
            'margin_top' => '20px',
            'margin_bottom' => '20px',
            'margin_left' => '20px',
            'margin_right' => '20px',
            'background_media_mode' => 'cover',
        ]);

        return $cmsBlockByteId;
    }

    private function deletePageSectionBlockAndSlot(): void
    {
        $cmsPageByteId = $this->getCmsPageId();
        $cmsSectionByteId = $this->getCmsSectionId($cmsPageByteId);
        $cmsBlockByteId = $this->getCmsBlockId();

        if ($cmsPageByteId !== null) {
            $this->connection->delete('cms_page', ['id' => $cmsPageByteId]);
            $this->connection->delete('cms_section', ['cms_page_id' => $cmsPageByteId]);
        }

        if ($cmsSectionByteId !== null) {
            $this->connection->delete('cms_block', ['cms_section_id' => $cmsSectionByteId]);
        }

        if ($cmsBlockByteId !== null) {
            $this->connection->delete('cms_block', ['id' => $cmsBlockByteId]);
            $this->connection->delete('cms_slot', ['cms_block_id' => $cmsPageByteId]);
        }
    }
}
