<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1752219159AddLanguageActive;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1752219159AddLanguageActive::class)]
class Migration1752219159AddLanguageActiveTest extends TestCase
{
    private const TEST_LANGUAGES = [
        'български език',
        'Čeština',
        'Dansk',
        'Ελληνικά',
        'Español',
        'Français',
        'हिन्दी',
        '한국어',
        'Nederlands',
        'Norsk',
        'Polski',
        'Türkçe',
        'Українська',
        'Tiếng Việt Nam',
    ];

    private const TEST_INACTIVE_LANGUAGE_NAMES = [
        '한국어',
        'Nederlands',
        'Tiếng Việt Nam',
    ];

    private const PACK_LANGUAGE_ENTITY_NAME = 'swag_language_pack_language';

    private readonly Connection $connection;

    private readonly Migration1752219159AddLanguageActive $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1752219159AddLanguageActive();

        $tryStatements = [
            'ALTER TABLE `language` DROP COLUMN `active`;',
            \sprintf('DROP TABLE `%s`;', self::PACK_LANGUAGE_ENTITY_NAME),
        ];

        foreach ($tryStatements as $statement) {
            try {
                $this->connection->executeStatement($statement);
            } catch (\Throwable) {
                // Column or table already does not exist, ignore
            }
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1752219159, $this->migration->getCreationTimestamp());
    }

    public function testAddColumn(): void
    {
        $this->migration->update($this->connection);
        static::assertSame(
            0,
            $this->getInactiveLanguagesCount(LanguageDefinition::ENTITY_NAME, 'active'),
            'All migrated languages should be active'
        );

        $this->connection->executeStatement(<<<'SQL'
            UPDATE `language`
            SET `active` = 0
            WHERE `active` != 0
            LIMIT 1
        SQL);

        $this->migration->update($this->connection);
        static::assertSame(
            1,
            $this->getInactiveLanguagesCount(LanguageDefinition::ENTITY_NAME, 'active'),
            'When `active` column already exists, it should not update any language'
        );
    }

    public function testAddColumnWithLanguagePackTable(): void
    {
        $this->createPackLanguageTable();

        $inactivePackLanguageCount = $this->getInactiveLanguagesCount(self::PACK_LANGUAGE_ENTITY_NAME, 'sales_channel_active');
        static::assertSame(3, $inactivePackLanguageCount);

        for ($i = 0; $i < 2; ++$i) {
            $this->migration->update($this->connection);

            $inactivePackLanguageCount = $this->getInactiveLanguagesCount(self::PACK_LANGUAGE_ENTITY_NAME, 'sales_channel_active');
            static::assertSame(3, $inactivePackLanguageCount);

            $inactiveLanguageCount = $this->getInactiveLanguagesCount(LanguageDefinition::ENTITY_NAME, 'active');
            static::assertSame(3, $inactiveLanguageCount);
        }

        static::assertSame(0, $this->getUnequalActiveStateCount());
    }

    private function getInactiveLanguagesCount(string $tableName, string $activeFieldName): int
    {
        return (int) $this->connection->fetchOne(\str_replace(
            ['#table#', '#activeFieldName#'],
            [$tableName, $activeFieldName],
            <<<'SQL'
                SELECT COUNT(*)
                FROM `#table#`
                WHERE `#activeFieldName#` = 0
            SQL
        ));
    }

    private function getUnequalActiveStateCount(): int
    {
        return (int) $this->connection->fetchOne(\str_replace(
            ['#language#', '#packLanguage#'],
            [LanguageDefinition::ENTITY_NAME, self::PACK_LANGUAGE_ENTITY_NAME],
            <<<'SQL'
                SELECT COUNT(*)
                FROM `#language#` l
                JOIN `#packLanguage#` pl ON l.`id` = pl.`language_id`
                WHERE l.`active` != pl.`sales_channel_active`;
            SQL
        ));
    }

    private function createPackLanguageTable(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `#table#` (
                `id`                    BINARY(16)  NOT NULL,
                `administration_active` TINYINT(1)  NULL DEFAULT '0',
                `sales_channel_active`  TINYINT(1)  NULL DEFAULT '0',
                `language_id`           BINARY(16)  NOT NULL,
                `created_at`            DATETIME(3) NOT NULL,
                `updated_at`            DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci;
        SQL;

        $this->connection->executeStatement(\str_replace(
            ['#table#'],
            [self::PACK_LANGUAGE_ENTITY_NAME],
            $sql,
        ));

        foreach (self::TEST_LANGUAGES as $languageName) {
            $languageId = Uuid::randomBytes();
            $localeId = Uuid::randomBytes();

            $localeData = [
                'id' => $localeId,
                'code' => Uuid::randomHex(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];

            $languageData = [
                'id' => $languageId,
                'name' => $languageName,
                'locale_id' => $localeId,
                'translation_code_id' => $localeId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];

            $languagePackData = [
                'id' => Uuid::randomBytes(),
                'sales_channel_active' => (\in_array($languageName, self::TEST_INACTIVE_LANGUAGE_NAMES, true) ? 0 : 1),
                'language_id' => $languageId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];

            $this->connection->insert(LocaleDefinition::ENTITY_NAME, $localeData);
            $this->connection->insert(LanguageDefinition::ENTITY_NAME, $languageData);
            $this->connection->insert(self::PACK_LANGUAGE_ENTITY_NAME, $languagePackData);
        }
    }
}
