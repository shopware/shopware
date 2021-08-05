<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

class ImportTranslationTraitTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ImportTranslationsTrait;

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

        $this->getContainer()
            ->get(Connection::class)
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
            $this->getContainer()->get(Connection::class)
        );

        $translations = $this->getContainer()->get(Connection::class)
            ->fetchAll(
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
        $data = [
            [
                'id' => $ids->create('german'),
                'name' => 'test',
                'localeId' => $this->getLocaleId('de-DE'),
                'translationCode' => [
                    'id' => Uuid::randomHex(),
                    'code' => 'te-te',
                    'name' => 'Test locale',
                    'territory' => 'test',
                ],
            ],
            [
                'id' => $ids->create('en-2'),
                'name' => 'test',
                'localeId' => $this->getLocaleId('en-GB'),
                'translationCode' => [
                    'id' => Uuid::randomHex(),
                    'code' => 'fr-te',
                    'name' => 'Test locale',
                    'territory' => 'test',
                ],
            ],
        ];

        $this->getContainer()->get('language.repository')
            ->create($data, Context::createDefaultContext());
    }

    private function getLocaleId(string $code): string
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchColumn('SELECT LOWER(HEX(id)) FROM locale WHERE code = :code', ['code' => $code]);
    }
}
