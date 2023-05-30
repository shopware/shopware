<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_3\Migration1607581276AddProductSearchConfigurationDefaults;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1607581276AddProductSearchConfigurationDefaults
 */
class Migration1607581276AddProductSearchConfigurationDefaultsTest extends TestCase
{
    private const GERMAN_LANGUAGE_NAME = 'Deutsch';

    private const ENGLISH_LANGUAGE_NAME = 'English';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->beginTransaction();
        $this->connection->executeStatement('DELETE FROM `product_search_config_field`');
        $this->connection->executeStatement('DELETE FROM `product_search_config`');
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
    }

    public function testExecuteTwoTimes(): void
    {
        static::assertEquals(0, $this->connection->fetchOne('SELECT COUNT(*) from product_search_config_field'));
        static::assertEquals(0, $this->connection->fetchOne('SELECT COUNT(*) from product_search_config'));

        $this->runMigration();

        $expectedConfigCount = $this->connection->fetchOne('SELECT COUNT(*) from product_search_config');
        $expectedConfigFieldCount = $this->connection->fetchOne('SELECT COUNT(*) from product_search_config_field');

        $this->runMigration();

        static::assertEquals($expectedConfigCount, $this->connection->fetchOne('SELECT COUNT(*) from product_search_config'));
        static::assertEquals($expectedConfigFieldCount, $this->connection->fetchOne('SELECT COUNT(*) from product_search_config_field'));
    }

    public function testMigrationConfigFields(): void
    {
        $searchConfigs = $this->fetchConfigFields();
        static::assertEmpty($searchConfigs);

        $this->runMigration();

        $searchConfigs = $this->fetchConfigFields();

        $expected = [
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'categories.customFields'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'categories.customFields'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'categories.name'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'categories.name'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'description'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'description'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'manufacturer.customFields'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'manufacturer.customFields'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'manufacturer.name'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'manufacturer.name'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'metaDescription'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'metaDescription'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'metaTitle'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'metaTitle'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'name'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'name'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'productNumber'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'productNumber'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'properties.name'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'properties.name'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'tags.name'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'tags.name'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'variantRestrictions'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'variantRestrictions'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'manufacturerNumber'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'manufacturerNumber'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'ean'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'ean'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'customSearchKeywords'],
            ['and_logic' => '1', 'min_search_length' => '2', 'field' => 'customSearchKeywords'],
        ];

        foreach ($searchConfigs as $config) {
            static::assertContains($config, $expected);
        }
    }

    public function testMigrationExcludedTerms(): void
    {
        $excludedTerms = $this->fetchExcludedTerms();
        static::assertEmpty($excludedTerms);

        $this->runMigration();

        $excludedTerms = $this->fetchExcludedTerms();
        $excludedTerms = FetchModeHelper::groupUnique($excludedTerms);

        $langName = strtolower(self::ENGLISH_LANGUAGE_NAME);
        if (\array_key_exists($langName, $excludedTerms)) {
            $enStopwords = require __DIR__ . '/../../../../src/Core/Migration/Fixtures/stopwords/en.php';
            static::assertEquals($enStopwords, json_decode((string) $excludedTerms[$langName]['terms'], null, 512, \JSON_THROW_ON_ERROR));
        }

        $langName = strtolower(self::GERMAN_LANGUAGE_NAME);
        if (\array_key_exists($langName, $excludedTerms)) {
            $deStopwords = require __DIR__ . '/../../../../src/Core/Migration/Fixtures/stopwords/de.php';
            static::assertEquals($deStopwords, json_decode((string) $excludedTerms[$langName]['terms'], null, 512, \JSON_THROW_ON_ERROR));
        }
    }

    public function testInsertCorrectLanguages(): void
    {
        $this->runMigration();

        $languages = $this->fetchLanguageIds();
        $productSearchConfigs = $this->fetchProductSearchConfigs();

        $languages = FetchModeHelper::groupUnique($languages);
        $productSearchConfigs = FetchModeHelper::groupUnique($productSearchConfigs);

        static::assertArrayHasKey(Defaults::LANGUAGE_SYSTEM, $productSearchConfigs);
        static::assertEquals($languages, $productSearchConfigs);
    }

    public function testMigrationWithVietnameseAsDefault(): void
    {
        $this->connection->executeStatement(
            'UPDATE `language`
                    SET name = "Vietnam", locale_id = :locale, translation_code_id = :locale
                    WHERE id = :id',
            ['locale' => $this->getLocaleId('vi-VN'), 'id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $this->runMigration();

        $fields = $this->connection->fetchAllAssociative(
            'SELECT config_field.field AS field FROM product_search_config_field AS config_field
            INNER JOIN product_search_config AS search_config
            ON search_config.id = config_field.product_search_config_id
            WHERE search_config.language_id = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $fields = array_column($fields, 'field');
        sort($fields);

        $expected = [
            'categories.customFields',
            'categories.name',
            'description',
            'manufacturer.customFields',
            'manufacturer.name',
            'metaDescription',
            'metaTitle',
            'name',
            'productNumber',
            'properties.name',
            'tags.name',
            'variantRestrictions',
            'manufacturerNumber',
            'ean',
            'customSearchKeywords',
        ];

        sort($expected);

        static::assertEquals($expected, $fields);

        $fields = $this->connection->fetchOne(
            'SELECT excluded_terms FROM product_search_config
            WHERE product_search_config.language_id = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        static::assertNull($fields);
    }

    public function testMigrationWithoutEnGb(): void
    {
        $deLiLocale = $this->getLocaleId('de-LI');

        $this->connection->update(
            'language',
            [
                'name' => 'ForeignLang',
                'locale_id' => $deLiLocale,
                'translation_code_id' => $deLiLocale,
            ],
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $this->runMigration();

        $fields = $this->connection->fetchAllAssociative(
            'SELECT config_field.field AS field FROM product_search_config_field AS config_field
            INNER JOIN product_search_config AS search_config
            ON search_config.id = config_field.product_search_config_id
            WHERE search_config.language_id = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $fields = array_column($fields, 'field');
        sort($fields);

        $expected = [
            'categories.customFields',
            'categories.name',
            'description',
            'manufacturer.customFields',
            'manufacturer.name',
            'metaDescription',
            'metaTitle',
            'name',
            'productNumber',
            'properties.name',
            'tags.name',
            'variantRestrictions',
            'manufacturerNumber',
            'ean',
            'customSearchKeywords',
        ];

        sort($expected);

        static::assertEquals($expected, $fields);

        $deDeLanguageId = $this->connection->fetchOne(
            'SELECT id FROM `language` WHERE `name` = :name',
            ['name' => self::GERMAN_LANGUAGE_NAME]
        );

        $fields = $this->connection->fetchAllAssociative(
            'SELECT config_field.field AS field FROM product_search_config_field AS config_field
            INNER JOIN product_search_config AS search_config
            ON search_config.id = config_field.product_search_config_id
            WHERE search_config.language_id = :id',
            ['id' => $deDeLanguageId]
        );

        $fields = array_column($fields, 'field');
        sort($fields);

        $expected = [
            'categories.customFields',
            'categories.name',
            'description',
            'manufacturer.customFields',
            'manufacturer.name',
            'metaDescription',
            'metaTitle',
            'name',
            'productNumber',
            'properties.name',
            'tags.name',
            'variantRestrictions',
            'manufacturerNumber',
            'ean',
            'customSearchKeywords',
        ];

        sort($expected);

        static::assertEquals($expected, $fields);
    }

    public function testMigrationWithoutEnGbOrDe(): void
    {
        $deLiLocaleId = $this->connection->fetchOne(
            'SELECT id FROM `locale` WHERE `code` = :code',
            ['code' => 'de-LI']
        );

        $this->connection->update(
            'language',
            [
                'name' => 'ForeignLang',
                'locale_id' => $deLiLocaleId,
                'translation_code_id' => $deLiLocaleId,
            ],
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $deLuLocaleId = $this->connection->fetchOne(
            'SELECT id FROM `locale` WHERE `code` = :code',
            ['code' => 'de-LU']
        );

        /** @var array<string, mixed> $deLuLanguage */
        $deLuLanguage = $this->connection->fetchAssociative(
            'SELECT * FROM `language` WHERE `name` = :name',
            ['name' => self::GERMAN_LANGUAGE_NAME]
        );

        $this->connection->update(
            'language',
            [
                'name' => 'OtherForeignLang',
                'locale_id' => $deLuLocaleId,
                'translation_code_id' => $deLuLocaleId,
            ],
            ['name' => self::GERMAN_LANGUAGE_NAME]
        );

        $this->runMigration();

        $fields = $this->connection->fetchAllAssociative(
            'SELECT config_field.field AS field FROM product_search_config_field AS config_field
            INNER JOIN product_search_config AS search_config
            ON search_config.id = config_field.product_search_config_id
            WHERE search_config.language_id = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $fields = array_column($fields, 'field');
        sort($fields);

        $expected = [
            'categories.customFields',
            'categories.name',
            'description',
            'manufacturer.customFields',
            'manufacturer.name',
            'metaDescription',
            'metaTitle',
            'name',
            'productNumber',
            'properties.name',
            'tags.name',
            'variantRestrictions',
            'manufacturerNumber',
            'ean',
            'customSearchKeywords',
        ];

        sort($expected);

        static::assertEquals($expected, $fields);

        $fields = $this->connection->fetchOne(
            'SELECT excluded_terms FROM product_search_config
            WHERE product_search_config.language_id = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        static::assertEmpty($fields);

        $fields = $this->connection->fetchAllAssociative(
            'SELECT config_field.field AS field FROM product_search_config_field AS config_field
            INNER JOIN product_search_config AS search_config
            ON search_config.id = config_field.product_search_config_id
            WHERE search_config.language_id = :id',
            ['id' => $deLuLanguage['id']]
        );

        static::assertEmpty($fields);
    }

    public function testMigrationWithOnlyDe(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `language` WHERE `id` != :defaultLanguage',
            ['defaultLanguage' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $deLocaleId = $this->connection->fetchOne(
            'SELECT id FROM `locale` WHERE `code` = :code',
            ['code' => 'de-DE']
        );

        $this->connection->update(
            'language',
            [
                'name' => self::GERMAN_LANGUAGE_NAME,
                'locale_id' => $deLocaleId,
                'translation_code_id' => $deLocaleId,
            ],
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $this->runMigration();

        $fields = $this->connection->fetchAllAssociative(
            'SELECT config_field.field AS field FROM product_search_config_field AS config_field
            INNER JOIN product_search_config AS search_config
            ON search_config.id = config_field.product_search_config_id
            WHERE search_config.language_id = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $fields = array_column($fields, 'field');
        sort($fields);

        $expected = [
            'categories.customFields',
            'categories.name',
            'description',
            'manufacturer.customFields',
            'manufacturer.name',
            'metaDescription',
            'metaTitle',
            'name',
            'productNumber',
            'properties.name',
            'tags.name',
            'variantRestrictions',
            'manufacturerNumber',
            'ean',
            'customSearchKeywords',
        ];

        sort($expected);

        static::assertEquals($expected, $fields);

        $fields = $this->connection->fetchOne(
            'SELECT excluded_terms FROM product_search_config
            WHERE product_search_config.language_id = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        $deStopwords = require __DIR__ . '/../../../../src/Core/Migration/Fixtures/stopwords/de.php';
        static::assertEquals($deStopwords, json_decode((string) $fields, null, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{array_key: string, language_id: string, name: string}[]
     */
    private function fetchLanguageIds(): array
    {
        /** @var array{array_key: string, language_id: string, name: string}[] $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as array_key, id as language_id, name FROM language ORDER BY name'
        );

        return $result;
    }

    /**
     * @return array{array_key: string, language_id: string, name: string}[]
     */
    private function fetchProductSearchConfigs(): array
    {
        /** @var array{array_key: string, language_id: string, name: string}[] $result */
        $result = $this->connection->fetchAllAssociative('
            SELECT LOWER(HEX(product_search_config.language_id)) as array_key, product_search_config.language_id as language_id, language.name as name
            FROM product_search_config
            INNER JOIN language
                ON language.id = product_search_config.language_id
            ORDER BY language.name
        ');

        return $result;
    }

    private function runMigration(): void
    {
        $migration = new Migration1607581276AddProductSearchConfigurationDefaults();
        $migration->update($this->connection);
    }

    /**
     * @return array{and_logic: string, min_search_length: string, field: string}[]
     */
    private function fetchConfigFields(): array
    {
        /** @var array{and_logic: string, min_search_length: string, field: string}[] $result */
        $result = $this->connection->fetchAllAssociative('
            SELECT config.and_logic, config.min_search_length, config_field.field
            FROM product_search_config as config
            INNER JOIN product_search_config_field as config_field
                ON config.id = config_field.product_search_config_id
            ORDER BY config_field.field ASC
        ');

        return $result;
    }

    /**
     * @return array{array_key: string, excluded_terms: string}[]
     */
    private function fetchExcludedTerms(): array
    {
        /** @var array{array_key: string, excluded_terms: string}[] $result */
        $result = $this->connection->fetchAllAssociative('
            SELECT LOWER(language.name) as array_key, config.excluded_terms as terms
            FROM product_search_config as config
            INNER JOIN language
                ON language.id = config.language_id
        ');

        return $result;
    }

    private function getLocaleId(string $code): string
    {
        return $this->connection->fetchOne('SELECT id FROM locale WHERE code = :code', ['code' => $code]);
    }
}
