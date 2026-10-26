<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_3\Migration1536233560BasicData;

/**
 * @internal
 *
 * MigrationCollection would be the natural covers target, but the migration job scopes
 * the coverage source to the src/*\/Migration directories, so no Framework class is a
 * valid target here; the replayed migrations must not receive smoke-level attribution either.
 */
#[Package('framework')]
#[CoversNothing]
class MigrationForeignDefaultLanguageTest extends TestCase
{
    use KernelTestBehaviour;

    private ?Connection $replayConnection = null;

    /**
     * No en-GB as language, de-LI as Default language and de-DE as second language
     * All en-GB contents should be written in de-LI and de-De contents will be written in de-DE
     */
    public function testMigrationWithoutEnGb(): void
    {
        $connection = $this->setupDB();

        $migrationCollection = $this->collectMigrations();

        foreach ($migrationCollection->getMigrationSteps() as $_className => $migration) {
            try {
                $migration->update($connection);
            } catch (\Exception $e) {
                static::fail($_className . \PHP_EOL . $e->getMessage());
            }

            if ($this->isBasicDataMigration($_className)) {
                $deLiLocale = $connection->fetchAssociative(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'de-LI',
                    ]
                );
                static::assertIsArray($deLiLocale);

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
        foreach ($migrationCollection->getMigrationSteps() as $_className => $migration) {
            try {
                $migration->updateDestructive($connection);
            } catch (\Exception $e) {
                static::fail($_className . \PHP_EOL . $e->getMessage());
            }
        }

        $templateDefault = $connection->fetchAssociative(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );
        static::assertIsArray($templateDefault);
        static::assertSame('Password recovery', $templateDefault['subject']);

        $deDeLanguage = $connection->fetchAssociative(
            'SELECT * FROM `language` WHERE `name` = :name',
            [
                'name' => 'Deutsch',
            ]
        );
        static::assertIsArray($deDeLanguage);

        $templateDeDe = $connection->fetchAssociative(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password-Wiederherstellung',
                'languageId' => $deDeLanguage['id'],
            ]
        );

        static::assertIsArray($templateDeDe);
        static::assertSame('Password-Wiederherstellung', $templateDeDe['subject']);
    }

    /**
     * No En-GB and no de-DE as language, de-LI as Default language and de-LU as second language
     * All en-GV contents should be written in de-LI and de-DE contents will not be written
     * de-LI will be left empty
     */
    public function testMigrationWithoutEnGbOrDe(): void
    {
        $connection = $this->setupDB();

        $migrationCollection = $this->collectMigrations();

        $deLuLanguage = [];

        foreach ($migrationCollection->getMigrationSteps() as $_className => $migration) {
            try {
                $migration->update($connection);
            } catch (\Exception $e) {
                static::fail($_className . \PHP_EOL . $e->getMessage());
            }

            if ($this->isBasicDataMigration($_className)) {
                $deLiLocale = $connection->fetchAssociative(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'de-LI',
                    ]
                );
                static::assertIsArray($deLiLocale);
                $connection->update(
                    'language',
                    [
                        'name' => 'ForeignLang',
                        'locale_id' => $deLiLocale['id'],
                        'translation_code_id' => $deLiLocale['id'],
                    ],
                    ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
                );
                $deLuLocale = $connection->fetchAssociative(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'de-LU',
                    ]
                );
                static::assertIsArray($deLuLocale);

                $deLuLanguage = $connection->fetchAssociative(
                    'SELECT * FROM `language` WHERE `name` = :name',
                    [
                        'name' => 'Deutsch',
                    ]
                );
                static::assertIsArray($deLuLanguage);

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

        foreach ($migrationCollection->getMigrationSteps() as $_className => $migration) {
            try {
                $migration->updateDestructive($connection);
            } catch (\Exception $e) {
                static::fail($_className . \PHP_EOL . $e->getMessage());
            }
        }

        $templateDefault = $connection->fetchAssociative(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );
        static::assertIsArray($templateDefault);
        static::assertSame('Password recovery', $templateDefault['subject']);

        $templateDeLu = $connection->fetchAssociative(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => $deLuLanguage['id'],
            ]
        );
        static::assertFalse($templateDeLu);
    }

    /**
     * En-GB and de-DE as language, but de-LI as Default language
     * All en-GB contents should be written in En-GB and de-LI and de-DE should be filled with de-DE contents
     */
    public function testMigrationWithEnGbAndDeButDifferentDefault(): void
    {
        $connection = $this->setupDB();

        $migrationCollection = $this->collectMigrations();
        $enGbId = Uuid::randomBytes();

        foreach ($migrationCollection->getMigrationSteps() as $_className => $migration) {
            try {
                $migration->update($connection);
            } catch (\Exception $e) {
                static::fail($_className . \PHP_EOL . $e->getMessage());
            }

            if ($this->isBasicDataMigration($_className)) {
                $deLiLocale = $connection->fetchAssociative(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'de-LI',
                    ]
                );
                static::assertIsArray($deLiLocale);
                $connection->update(
                    'language',
                    [
                        'name' => 'ForeignLang',
                        'locale_id' => $deLiLocale['id'],
                        'translation_code_id' => $deLiLocale['id'],
                    ],
                    ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
                );
                $enGbLocale = $connection->fetchAssociative(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'en-GB',
                    ]
                );
                static::assertIsArray($enGbLocale);

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

        foreach ($migrationCollection->getMigrationSteps() as $_className => $migration) {
            try {
                $migration->updateDestructive($connection);
            } catch (\Exception $e) {
                static::fail($_className . \PHP_EOL . $e->getMessage());
            }
        }

        $templateDefault = $connection->fetchAssociative(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );
        static::assertIsArray($templateDefault);
        static::assertSame('Password recovery', $templateDefault['subject']);

        $templateEnGb = $connection->fetchAssociative(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => $enGbId,
            ]
        );
        static::assertIsArray($templateEnGb);
        static::assertSame('Password recovery', $templateEnGb['subject']);
    }

    #[After]
    public function dropReplayDatabase(): void
    {
        $this->replayConnection?->close();
        $this->replayConnection = null;

        static::getContainer()->get(Connection::class)->executeStatement('DROP DATABASE IF EXISTS `' . $this->replayDatabaseName() . '`');
    }

    private function isBasicDataMigration(string $className): bool
    {
        return $className === Migration1536233560BasicData::class;
    }

    private function collectMigrations(): MigrationCollection
    {
        return static::getContainer()
            ->get(MigrationCollectionLoader::class)
            ->collectAllForVersion(
                static::getContainer()->getParameter('kernel.shopware_version'),
                MigrationCollectionLoader::VERSION_SELECTION_ALL
            );
    }

    private function setupDB(): Connection
    {
        $orgConnection = static::getContainer()->get(Connection::class);
        $databaseName = $this->replayDatabaseName();

        $orgConnection->executeStatement('DROP DATABASE IF EXISTS `' . $databaseName . '`');
        $orgConnection->executeStatement('CREATE DATABASE `' . $databaseName . '` DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci');

        $this->replayConnection = new Connection(
            array_merge($orgConnection->getParams(), ['dbname' => $databaseName]),
            $orgConnection->getDriver(),
            $orgConnection->getConfiguration(),
        );

        $dumpFile = file_get_contents(__DIR__ . '/../../../src/Core/schema.sql');
        static::assertIsString($dumpFile);

        $this->replayConnection->executeStatement($dumpFile);

        return $this->replayConnection;
    }

    // the replay runs on its own database, so the shared test database and its transaction stay untouched
    private function replayDatabaseName(): string
    {
        return static::getContainer()->get(Connection::class)->getDatabase() . '_no_migrations';
    }
}
