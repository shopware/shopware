<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_3\Migration1599134496FixImportExportProfilesForGermanLanguage;

/**
 * @internal
 */
#[Package('core')]
class Migration1599134496FixImportExportProfilesForGermanLanguageTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private EntityRepository $importExportProfileRepository;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->importExportProfileRepository = $this->getContainer()->get('import_export_profile.repository');

        $this->connection->executeStatement('DELETE FROM import_export_profile');
        parent::setUp();
    }

    public function testMigrateGermanIsStandard(): void
    {
        // This is intended, the current german language ID will become the english language id afterwards
        $englishId = $this->getDeDeLanguageId();
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$englishId, Defaults::LANGUAGE_SYSTEM]);
        $this->importExportProfileRepository->create($this->getEnglishData(), $context);

        $this->setDefaultLanguageToLocale('de-DE');
        $this->executeMigration();

        $translations = $this->connection->fetchAllAssociative('SELECT * FROM import_export_profile_translation');
        static::assertCount(12, $translations);

        $labels = array_column($translations, 'label');
        static::assertContains('Standardprofil Variantenkonfiguration', $labels);
    }

    public function testMigrateEnglishIsStandard(): void
    {
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $this->importExportProfileRepository->create($this->getEnglishData(), $context);

        $this->executeMigration();

        $translations = $this->connection->fetchAllAssociative('SELECT * FROM import_export_profile_translation');
        static::assertCount(12, $translations);

        $labels = array_column($translations, 'label');
        static::assertContains('Standardprofil Variantenkonfiguration', $labels);
    }

    public function testMigratePolishIsStandard(): void
    {
        $englishId = Uuid::randomBytes();

        $this->simulateThirdLanguagePolishIsDefault($englishId);

        $polishData = $this->getPolishAndEnglishData(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($englishId));
        $englishContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$englishId, Defaults::LANGUAGE_SYSTEM]);
        $this->importExportProfileRepository->create($polishData, $englishContext);

        $this->executeMigration();

        $translations = $this->connection->fetchAllAssociative('SELECT * FROM import_export_profile_translation');
        static::assertCount(18, $translations);

        $labels = array_column($translations, 'label');
        static::assertContains('Standardprofil Variantenkonfiguration', $labels);
    }

    public function testMigrateDoesntBreakCorrectDataset(): void
    {
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $this->importExportProfileRepository->create($this->getEnglishData(), $context);

        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM]);
        $this->importExportProfileRepository->upsert($this->getGermanData(), $context);

        $this->executeMigration();

        $translations = $this->connection->fetchAllAssociative('SELECT * FROM import_export_profile_translation');
        static::assertCount(12, $translations);

        $labels = array_column($translations, 'label');
        static::assertContains('Standardprofil Variantenkonfiguration', $labels);
    }

    public function testMigrateDoesntTouchCustomEntries(): void
    {
        $englishData = $this->getEnglishData();
        $englishData[] = [
            'id' => Uuid::randomHex(),
            'name' => 'My custom entry',
            'label' => 'Custom entry',
            'systemDefault' => false,
            'sourceEntity' => 'product',
            'fileType' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
        ];

        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $this->importExportProfileRepository->create($englishData, $context);

        $this->executeMigration();

        $translations = $this->connection->fetchAllAssociative('SELECT * FROM import_export_profile_translation');
        static::assertCount(13, $translations);

        $labels = array_column($translations, 'label');
        static::assertContains('Standardprofil Variantenkonfiguration', $labels);
    }

    public function testMigrateWithoutGermanLanguage(): void
    {
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
        $this->importExportProfileRepository->create($this->getEnglishData(), $context);

        $this->removeLanguageByIsoCode('de-DE');

        $this->executeMigration();

        $translations = $this->connection->fetchAllAssociative('SELECT * FROM import_export_profile_translation');
        static::assertCount(6, $translations);

        $labels = array_column($translations, 'label');
        static::assertNotContains('Standardprofil Variantenkonfiguration', $labels);
    }

    private function executeMigration(): void
    {
        $migration = new Migration1599134496FixImportExportProfilesForGermanLanguage();
        $migration->update($this->connection);
    }

    private function setDefaultLanguageToLocale(string $localeCode): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0;');

        $localeId = $this->getLocaleIdForCode($localeCode);

        $defaultLanguageId = Defaults::LANGUAGE_SYSTEM;

        $defaultLocaleId = $this->getLocaleFromDefaultLanguage($defaultLanguageId);
        $languageId = $this->getLanguageIdForLocale($localeId);

        // Sets new default
        $stmt = $this->connection->prepare('UPDATE `language` SET locale_id = :localeId, translation_code_id = :tempId WHERE id = :defaultLanguageId');
        $stmt->executeStatement([
            'localeId' => Uuid::fromHexToBytes($localeId),
            'defaultLanguageId' => Uuid::fromHexToBytes($defaultLanguageId),
            'tempId' => Uuid::randomBytes(),
        ]);

        // Sets old default to the previous locale ID of the new default
        $stmt = $this->connection->prepare('UPDATE `language` SET locale_id = :localeId, translation_code_id = :localeId WHERE id = :languageId');
        $stmt->executeStatement([
            'localeId' => Uuid::fromHexToBytes($defaultLocaleId),
            'languageId' => Uuid::fromHexToBytes($languageId),
        ]);

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    private function getLanguageIdForLocale(string $localeId): string
    {
        $languageId = $this->connection->fetchOne('SELECT id FROM `language` WHERE locale_id = :localeId', [
            'localeId' => Uuid::fromHexToBytes($localeId),
        ]);

        static::assertNotFalse($languageId);

        return Uuid::fromBytesToHex((string) $languageId);
    }

    private function getLocaleFromDefaultLanguage(string $defaultLanguageId): string
    {
        $localeId = $this->connection->fetchOne('SELECT locale_id FROM `language` WHERE id = :id', [
            'id' => Uuid::fromHexToBytes($defaultLanguageId),
        ]);

        static::assertNotFalse($localeId);

        return Uuid::fromBytesToHex((string) $localeId);
    }

    private function getLocaleIdForCode(string $localeCode): string
    {
        $localeId = $this->connection->fetchOne('SELECT id FROM `locale` WHERE `code` = :code', [
            'code' => $localeCode,
        ]);

        static::assertNotFalse($localeId);

        return Uuid::fromBytesToHex((string) $localeId);
    }

    /**
     * @return list<array{id: string, name: string, label: string, systemDefault: bool, sourceEntity: string, fileType: string, delimiter: string, enclosure: string}>
     */
    private function getEnglishData(): array
    {
        return [
            [
                'id' => '0c97a88fe59b49789496d27942948e19',
                'name' => 'Default variant configuration settings',
                'label' => 'Default variant configuration settings',
                'systemDefault' => true,
                'sourceEntity' => 'product_configurator_setting',
                'fileType' => 'text/csv',
                'delimiter' => ';',
                'enclosure' => '"',
            ],
            [
                'id' => '68b0c53efab74b959f6da17f856ab8b9',
                'name' => 'Default product',
                'label' => 'Default product',
                'systemDefault' => true,
                'sourceEntity' => 'product',
                'fileType' => 'text/csv',
                'delimiter' => ';',
                'enclosure' => '"',
            ],
            [
                'id' => '6cb7a1c19cb94817a108ca48d935bf84',
                'name' => 'Default properties',
                'label' => 'Default properties',
                'systemDefault' => true,
                'sourceEntity' => 'property_group_option',
                'fileType' => 'text/csv',
                'delimiter' => ';',
                'enclosure' => '"',
            ],
            [
                'id' => 'b3d5f7a0d84c40088a225691a182afcd',
                'name' => 'Default newsletter recipient',
                'label' => 'Default newsletter recipient',
                'systemDefault' => true,
                'sourceEntity' => 'newsletter_recipient',
                'fileType' => 'text/csv',
                'delimiter' => ';',
                'enclosure' => '"',
            ],
            [
                'id' => 'db82e8dadb2a40168e52bd745d9d28ff',
                'name' => 'Default category',
                'label' => 'Default category',
                'systemDefault' => true,
                'sourceEntity' => 'category',
                'fileType' => 'text/csv',
                'delimiter' => ';',
                'enclosure' => '"',
            ],
            [
                'id' => 'de4ab3bf0d6b4b62a2f79053c5a06b8e',
                'name' => 'Default media',
                'label' => 'Default media',
                'systemDefault' => true,
                'sourceEntity' => 'media',
                'fileType' => 'text/csv',
                'delimiter' => ';',
                'enclosure' => '"',
            ],
        ];
    }

    /**
     * @return list<array{id: string, name: string, label: string, systemDefault: bool, sourceEntity: string, fileType: string, delimiter: string, enclosure: string}>
     */
    private function getGermanData(): array
    {
        $germanData = [
            'Default category' => 'Standardprofil Kategorie',
            'Default media' => 'Standardprofil Medien',
            'Default variant configuration settings' => 'Standardprofil Variantenkonfiguration',
            'Default newsletter recipient' => 'Standardprofil Newsletter-Empfänger',
            'Default properties' => 'Standardprofil Eigenschaften',
            'Default product' => 'Standardprofil Produkt',
        ];
        $englishData = $this->getEnglishData();

        foreach ($englishData as &$englishDatum) {
            $englishDatum['label'] = $germanData[$englishDatum['name']];
        }
        unset($englishDatum);

        return $englishData;
    }

    private function simulateThirdLanguagePolishIsDefault(string $englishId): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0;');

        $this->connection->executeStatement('DELETE FROM `language`');

        $insertSql = <<<'SQL'
            INSERT INTO `language` (`id`, `name`, `locale_id`, `translation_code_id`, `created_at`)
            VALUES (:id, :name, :localeId, :translationCodeId, NOW())
SQL;

        $stmt = $this->connection->prepare($insertSql);

        $polishLocaleId = Uuid::fromHexToBytes($this->getLocaleIdForCode('pl-PL'));
        $englishLocaleId = Uuid::fromHexToBytes($this->getLocaleIdForCode('en-GB'));
        $germanLocaleId = Uuid::fromHexToBytes($this->getLocaleIdForCode('de-DE'));
        $languageData = [
            [
                ':id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                ':name' => 'Polish',
                ':localeId' => $polishLocaleId,
                ':translationCodeId' => $polishLocaleId,
            ], [
                ':id' => $englishId,
                ':name' => 'English',
                ':localeId' => $englishLocaleId,
                ':translationCodeId' => $englishLocaleId,
            ], [
                ':id' => Uuid::randomBytes(),
                ':name' => 'German',
                ':localeId' => $germanLocaleId,
                ':translationCodeId' => $germanLocaleId,
            ],
        ];

        foreach ($languageData as $data) {
            $stmt->executeStatement($data);
        }

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * @return list<array{id: string, name: string, label: string, systemDefault: bool, sourceEntity: string, fileType: string, delimiter: string, enclosure: string}>
     */
    private function getPolishAndEnglishData(string $polishId, string $englishId): array
    {
        $englishData = $this->getEnglishData();

        foreach ($englishData as &$data) {
            $data['translations'][$polishId] = [
                'label' => 'PL' . $data['label'],
            ];
            $data['translations'][$englishId] = [
                'label' => $data['label'],
            ];
            unset($data['label']);
        }
        unset($data);

        return $englishData;
    }

    private function removeLanguageByIsoCode(string $iso): void
    {
        $this->connection->executeStatement(
            'DELETE `language`
            FROM `language`
            INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id`
            WHERE `locale`.`code` = :isoCode
            ',
            ['isoCode' => $iso]
        );
    }
}
