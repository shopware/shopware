<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(ImportTranslationsTrait::class)]
class ImportTranslationTraitTest extends TestCase
{
    use ImportTranslationsTrait;
    use MigrationTestTrait;

    public function testEnglishDefault(): void
    {
        $ids = new IdsCollection();

        $this->createLanguages($ids);

        $data = [
            'id' => Uuid::fromHexToBytes($ids->create('category')),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'type' => 'category',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        KernelLifecycleManager::getConnection()
            ->insert('category', $data);

        $this->importTranslation(
            'category_translation',
            new Translations(
                [
                    'category_id' => Uuid::fromHexToBytes($ids->get('category')),
                    'category_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    'name' => 'de name',
                ],
                [
                    'category_id' => Uuid::fromHexToBytes($ids->get('category')),
                    'category_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                    'name' => 'en name',
                ]
            ),
            KernelLifecycleManager::getConnection()
        );

        $translations = KernelLifecycleManager::getConnection()
            ->fetchAllAssociative(
                'SELECT LOWER(HEX(language_id)) as array_key, category_translation.*  FROM category_translation WHERE category_id = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('category'))]
            );

        $translations = FetchModeHelper::groupUnique($translations);

        static::assertArrayHasKey(Defaults::LANGUAGE_SYSTEM, $translations);
        static::assertArrayHasKey($ids->get('german'), $translations);
        static::assertArrayHasKey($ids->get('en-2'), $translations);

        static::assertEquals('en name', $translations[Defaults::LANGUAGE_SYSTEM]['name']);
        static::assertEquals('en name', $translations[$ids->get('en-2')]['name']);
        static::assertEquals('de name', $translations[$ids->get('german')]['name']);
    }

    private function createLanguages(IdsCollection $ids): void
    {
        $localeData = [
            [
                'id' => Uuid::fromHexToBytes($ids->create('firstLocale')),
                'code' => 'te-te',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'id' => Uuid::fromHexToBytes($ids->create('secondLocale')),
                'code' => 'fr-te',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        ];

        $languageData = [
            [
                'id' => Uuid::fromHexToBytes($ids->create('german')),
                'name' => 'test',
                'locale_id' => $this->getLocaleId('de-DE'),
                'translation_code_id' => Uuid::fromHexToBytes($ids->get('firstLocale')),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'id' => Uuid::fromHexToBytes($ids->create('en-2')),
                'name' => 'test',
                'locale_id' => $this->getLocaleId('en-GB'),
                'translation_code_id' => Uuid::fromHexToBytes($ids->get('secondLocale')),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        ];

        $connection = KernelLifecycleManager::getConnection();
        $connection->insert('locale', $localeData[0]);
        $connection->insert('locale', $localeData[1]);

        $connection->insert('language', $languageData[0]);
        $connection->insert('language', $languageData[1]);
    }

    private function getLocaleId(string $code): string
    {
        return KernelLifecycleManager::getConnection()
            ->fetchOne('SELECT id FROM locale WHERE code = :code', ['code' => $code]);
    }
}
