<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Migration1536233560BasicData;

/**
 * @group slow
 */
class MigrationForeignDefaultLanguageTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private $databaseName = 'shopware';

    public function setUp(): void
    {
        $this->databaseName = substr(parse_url($_SERVER['DATABASE_URL'])['path'], 1) . '_no_migrations';
        $testDb = ($_SERVER['DATABASE_URL'] ?? '') . '_no_migrations';
        putenv('DATABASE_URL=' . $testDb);
        $_ENV['DATABASE_URL'] = $testDb;
        $_SERVER['DATABASE_URL'] = $testDb;
    }

    public function tearDown(): void
    {
        $testDb = str_replace('_no_migrations', '', $_SERVER['DATABASE_URL']);
        putenv('DATABASE_URL=' . $testDb);
        $_ENV['DATABASE_URL'] = $testDb;
        $_SERVER['DATABASE_URL'] = $testDb;
    }

    /**
     * No en-GB as language, de-LI as Default language and de-DE as second language
     * All en-GB contents should be written in de-LI and de-De contents will be written in de-DE
     */
    public function testMigrationWithoutEnGb(): void
    {
        $orgConnection = $this->getContainer()->get(Connection::class);

        $connection = $this->setupDB($orgConnection);

        $migrationCollection = $this->getContainer()->get(MigrationCollectionLoader::class)->collect('core');

        /* @var MigrationStep $migration */
        foreach ($migrationCollection->getMigrationSteps() as $_className => $migrationClass) {
            $migration = new $migrationClass();

            try {
                $migration->update($connection);
                $migration->updateDestructive($connection);
            } catch (\Exception $e) {
                static::fail($_className . PHP_EOL . $e->getMessage());
            }

            if ($_className === Migration1536233560BasicData::class) {
                $deLiLocale = $connection->fetchAssoc(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'de-LI',
                    ]
                );
                $connection->update(
                    'language',
                    [
                        'name' => 'ForeignLang',
                        'locale_id' => $deLiLocale['id'],
                        'translation_code_id' => $deLiLocale['id'],
                    ],
                    ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
                );
            }
        }

        $templateDefault = $connection->fetchAssoc(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );
        static::assertEquals('Password recovery', $templateDefault['subject']);

        $deDeLanguage = $connection->fetchAssoc(
            'SELECT * FROM `language` WHERE `name` = :name',
            [
                'name' => 'Deutsch',
            ]
        );

        $templateDeDe = $connection->fetchAssoc(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password Wiederherstellung',
                'languageId' => $deDeLanguage['id'],
            ]
        );
        static::assertEquals('Password Wiederherstellung', $templateDeDe['subject']);
    }

    /**
     * No En-GB and no de-DE as language, de-LI as Default language and de-LU as second language
     * All en-GV contents should be written in de-LI and de-DE contents will not be written
     * de-LI will be left empty
     */
    public function testMigrationWithoutEnGbOrDe(): void
    {
        $orgConnection = $this->getContainer()->get(Connection::class);

        $connection = $this->setupDB($orgConnection);

        $migrationCollection = $this->getContainer()->get(MigrationCollectionLoader::class)->collect('core');

        $deLuLanguage = [];

        /* @var MigrationStep $migration */
        foreach ($migrationCollection->getMigrationSteps() as $_className => $migrationClass) {
            $migration = new $migrationClass();

            try {
                $migration->update($connection);
                $migration->updateDestructive($connection);
            } catch (\Exception $e) {
                static::fail($_className . PHP_EOL . $e->getMessage());
            }

            if ($_className === Migration1536233560BasicData::class) {
                $deLiLocale = $connection->fetchAssoc(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'de-LI',
                    ]
                );
                $connection->update(
                    'language',
                    [
                        'name' => 'ForeignLang',
                        'locale_id' => $deLiLocale['id'],
                        'translation_code_id' => $deLiLocale['id'],
                    ],
                    ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
                );
                $deLuLocale = $connection->fetchAssoc(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'de-LU',
                    ]
                );

                $deLuLanguage = $connection->fetchAssoc(
                    'SELECT * FROM `language` WHERE `name` = :name',
                    [
                        'name' => 'Deutsch',
                    ]
                );

                $connection->update(
                    'language',
                    [
                        'name' => 'OtherForeignLang',
                        'locale_id' => $deLuLocale['id'],
                        'translation_code_id' => $deLuLocale['id'],
                    ],
                    ['name' => 'Deutsch']
                );
            }
        }

        $templateDefault = $connection->fetchAssoc(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );
        static::assertEquals('Password recovery', $templateDefault['subject']);

        $templateDeLu = $connection->fetchAssoc(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => $deLuLanguage['id'],
            ]
        );
        static::assertEmpty($templateDeLu);
    }

    /**
     * En-GB and de-DE as language, but de-LI as Default language
     * All en-GB contents should be written in En-GB and de-LI and de-DE should be filled with de-DE contents
     */
    public function testMigrationWithEnGbAndDeButDifferentDefault(): void
    {
        $orgConnection = $this->getContainer()->get(Connection::class);

        $connection = $this->setupDB($orgConnection);

        $migrationCollection = $this->getContainer()->get(MigrationCollectionLoader::class)->collect('core');
        $enGbId = Uuid::randomBytes();

        /* @var MigrationStep $migration */
        foreach ($migrationCollection->getMigrationSteps() as $_className => $migrationClass) {
            $migration = new $migrationClass();

            try {
                $migration->update($connection);
                $migration->updateDestructive($connection);
            } catch (\Exception $e) {
                static::fail($_className . PHP_EOL . $e->getMessage());
            }

            if ($_className === Migration1536233560BasicData::class) {
                $deLiLocale = $connection->fetchAssoc(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'de-LI',
                    ]
                );
                $connection->update(
                    'language',
                    [
                        'name' => 'ForeignLang',
                        'locale_id' => $deLiLocale['id'],
                        'translation_code_id' => $deLiLocale['id'],
                    ],
                    ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
                );
                $enGbLocale = $connection->fetchAssoc(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'en-GB',
                    ]
                );

                $connection->insert(
                    'language',
                    [
                        'id' => $enGbId,
                        'name' => 'English',
                        'locale_id' => $enGbLocale['id'],
                        'translation_code_id' => $enGbLocale['id'],
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );
            }
        }

        $templateDefault = $connection->fetchAssoc(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );
        static::assertEquals('Password recovery', $templateDefault['subject']);

        $templateEnGb = $connection->fetchAssoc(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => $enGbId,
            ]
        );
        static::assertEquals('Password recovery', $templateEnGb['subject']);
    }

    private function setupDB(Connection $orgConnection): Connection
    {
        //Be sure that we are on the no migrations db
        static::assertStringContainsString('_no_migrations', $this->databaseName, 'Wrong DB ' . $this->databaseName);

        $orgConnection->exec('DROP DATABASE IF EXISTS `' . $this->databaseName . '`');

        $orgConnection->exec('CREATE DATABASE `' . $this->databaseName . '` DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci');

        $connection = new Connection(
            array_merge(
                $orgConnection->getParams(),
                [
                    'url' => $_SERVER['DATABASE_URL'],
                    'dbname' => $this->databaseName,
                ]
            ),
            $orgConnection->getDriver(),
            $orgConnection->getConfiguration(),
            $orgConnection->getEventManager()
        );

        if (file_exists(__DIR__ . '/../../schema.sql')) {
            $dumpFile = file_get_contents(__DIR__ . '/../../schema.sql');
        } else {
            static::fail('schema.sql not found in ' . __DIR__ . '/../../schema.sql');
        }

        $connection->exec($dumpFile);

        return $connection;
    }
}
