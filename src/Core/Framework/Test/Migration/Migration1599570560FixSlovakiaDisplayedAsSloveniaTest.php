<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_3\Migration1599570560FixSlovakiaDisplayedAsSlovenia;

/**
 * @internal
 */
class Migration1599570560FixSlovakiaDisplayedAsSloveniaTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private string $languageEN;

    private string $languageDE;

    private ?string  $countryIdSlovakia = null;

    private Migration1599570560FixSlovakiaDisplayedAsSlovenia $migration;

    /**
     * @var array<string, bool|string>
     *
     * no transaction necessary since only this data will be changed and later reset
     */
    private array $resetData = [
        'SlovakiaAvailable' => true,
        'languageEnAvailable' => true,
        'languageDeAvailable' => true,
        'EnTranslation' => 'Slovakia',
        'DeTranslation' => 'Slowakei',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->migration = new Migration1599570560FixSlovakiaDisplayedAsSlovenia();

        $this->languageEN = $this->connection->fetchOne('SELECT language.id FROM language INNER JOIN locale
            ON language.translation_code_id = locale.id AND locale.code = \'en-GB\'');

        $this->languageDE = $this->connection->fetchOne('SELECT language.id FROM language INNER JOIN locale
            ON language.translation_code_id = locale.id AND locale.code = \'de-DE\'');

        $this->countryIdSlovakia = $this->connection->fetchOne('SELECT id from country WHERE iso3 = \'SVK\'');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->setDB($this->resetData);
    }

    /**
     * @dataProvider migrationCases
     *
     * @param array<string, bool|string> $data
     */
    public function testMigration(array $data): void
    {
        $this->setDB($data);

        $updated_at = $this->getUpdatedAt();

        if (!$data['SlovakiaAvailable']) {
            // country not available makes the migration not find the country. no changes should be made
            $this->checksWithNoChangesExpected();
        } else {
            $this->migration->update($this->connection);
            if ($data['languageEnAvailable']) {
                $this->checkMigrationForAvailableLanguage($this->languageEN, (string) $data['expectedEnTranslation'], (string) $data['EnTranslation'], $updated_at['en']);
            } else {
                //language is not Available so nothing should change for this language
                $this->checkMigrationForUnavailableLanguage($this->languageEN);
            }
            if ($data['languageDeAvailable']) {
                $this->checkMigrationForAvailableLanguage($this->languageDE, (string) $data['expectedDeTranslation'], (string) $data['DeTranslation'], $updated_at['de']);
            } else {
                //language is not Available so nothing should change
                $this->checkMigrationForUnavailableLanguage($this->languageDE);
            }
        }
    }

    /**
     * runs the migration twice, changes should only happen the first time
     */
    public function testMigrationTwice(): void
    {
        $data
            = [
                'SlovakiaAvailable' => true, 'languageEnAvailable' => true, 'languageDeAvailable' => true,
                'EnTranslation' => 'Slovenia', 'DeTranslation' => 'Slowenien', 'expectedEnTranslation' => 'Slovakia',
                'expectedDeTranslation' => 'Slowakei',
            ];
        $this->setDB($data);
        $updated_at = $this->getUpdatedAt();

        $this->migration->update($this->connection);

        $this->checkMigrationForAvailableLanguage($this->languageEN, $data['expectedEnTranslation'], $data['EnTranslation'], $updated_at['en']);
        $this->checkMigrationForAvailableLanguage($this->languageDE, $data['expectedDeTranslation'], $data['DeTranslation'], $updated_at['de']);

        //run the migration a second time, nothing should change
        $this->checksWithNoChangesExpected();
    }

    /**
     * @return list<array{0: array<string, bool|string>}>
     *                    SlovakiaAvailable -> changes if the Migration can find the country
     *                    languageEnAvailable -> English language Available
     *                    languageDeAvailable -> German language Available
     *                    EnTranslation -> sets the name of the country in english
     *                    DeTranslation -> sets the name of the country in german
     *                    expectedEnTranslation -> What the translation should be after the Migration
     */
    public static function migrationCases(): array
    {
        return [
            //already correct/modified, should be no changes
            [
                [
                    'SlovakiaAvailable' => true, 'languageEnAvailable' => true, 'languageDeAvailable' => true,
                    'EnTranslation' => 'Slovakia', 'DeTranslation' => 'Slowakei', 'expectedEnTranslation' => 'Slovakia',
                    'expectedDeTranslation' => 'Slowakei',
                ],
            ],
            [
                [
                    'SlovakiaAvailable' => true, 'languageEnAvailable' => true, 'languageDeAvailable' => true,
                    'EnTranslation' => 'CustomerChangesEN', 'DeTranslation' => 'CustomerChangesDE', 'expectedEnTranslation' => 'CustomerChangesEN',
                    'expectedDeTranslation' => 'CustomerChangesDE',
                ],
            ],
            //Old wrong translations, should be fixed afterwards
            [
                [
                    'SlovakiaAvailable' => true, 'languageEnAvailable' => true, 'languageDeAvailable' => true,
                    'EnTranslation' => 'Slovenia', 'DeTranslation' => 'Slowenien', 'expectedEnTranslation' => 'Slovakia',
                    'expectedDeTranslation' => 'Slowakei',
                ],
            ],
            //Languages not available, no changes should happen
            [
                [
                    'SlovakiaAvailable' => true, 'languageEnAvailable' => false, 'languageDeAvailable' => true,
                    'EnTranslation' => 'stuffNotToBeChanged', 'DeTranslation' => 'Slowenien', 'expectedEnTranslation' => 'Slovakia',
                    'expectedDeTranslation' => 'Slowakei',
                ],
            ],
            [
                [
                    'SlovakiaAvailable' => true, 'languageEnAvailable' => true, 'languageDeAvailable' => false,
                    'EnTranslation' => 'Slovenia', 'DeTranslation' => 'Slowenien', 'expectedEnTranslation' => 'Slovakia',
                    'expectedDeTranslation' => 'Slowakei',
                ],
            ],
            [
                [
                    'SlovakiaAvailable' => true, 'languageEnAvailable' => false, 'languageDeAvailable' => false,
                    'EnTranslation' => 'stuffNotToBeChanged', 'DeTranslation' => 'stuffNotToBeChanged', 'expectedEnTranslation' => 'Slovakia',
                    'expectedDeTranslation' => 'Slowakei',
                ],
            ],
            //Country not available, no changes should happen
            [
                [
                    'SlovakiaAvailable' => false, 'languageEnAvailable' => true, 'languageDeAvailable' => true,
                    'EnTranslation' => 'stuffNotToBeChanged', 'DeTranslation' => 'stuffNotToBeChanged', 'expectedEnTranslation' => 'Slovakia',
                    'expectedDeTranslation' => 'Slowakei',
                ],
            ],
            [
                [
                    'SlovakiaAvailable' => false, 'languageEnAvailable' => true, 'languageDeAvailable' => true,
                    'EnTranslation' => 'stuffNotToBeChanged', 'DeTranslation' => 'stuffNotToBeChanged', 'expectedEnTranslation' => 'Slovakia',
                    'expectedDeTranslation' => 'Slowakei',
                ],
            ],
        ];
    }

    /**
     * !Runs the migration itself!
     * checks if anything in the country_translation table has changed
     */
    private function checksWithNoChangesExpected(): void
    {
        $dbData = $this->connection->fetchAllAssociative('SELECT * FROM country_translation ORDER BY country_id');
        $expectedHash = md5(serialize($dbData));

        $this->migration->update($this->connection);

        $dbData = $this->connection->fetchAllAssociative('SELECT * FROM country_translation ORDER BY country_id');
        $actualHash = md5(serialize($dbData));

        static::assertSame($expectedHash, $actualHash, 'The data has changed');
    }

    /**
     * @return array{de: string, en: string}
     */
    private function getUpdatedAt(): array
    {
        $updated_atEN = $this->connection->fetchOne(
            'SELECT updated_at FROM country_translation WHERE language_id = ? AND country_id = ?',
            [$this->languageEN, $this->countryIdSlovakia]
        );
        $updated_atDE = $this->connection->fetchOne(
            'SELECT updated_at FROM country_translation WHERE language_id = ? AND country_id = ?',
            [$this->languageDE, $this->countryIdSlovakia]
        );

        return ['en' => $updated_atEN, 'de' => $updated_atDE];
    }

    private function checkMigrationForAvailableLanguage(string $languageId, string $expectedTranslation, string $oldTranslation, string $oldUpdateDate): void
    {
        $stmt = $this->connection->prepare('SELECT name, updated_at FROM country_translation WHERE language_id = ? AND country_id = ?');
        /** @var array{name: string, updated_at: string} $actualData */
        $actualData = $stmt->executeQuery([$languageId, $this->countryIdSlovakia])->fetchAssociative();
        static::assertEquals($expectedTranslation, $actualData['name']);
        //If the data has changed the updated_at field also has to change
        if ($expectedTranslation !== $oldTranslation) {
            static::assertGreaterThan($oldUpdateDate, $actualData['updated_at']);
        } else {
            static::assertEquals($oldUpdateDate, $actualData['updated_at']);
        }
    }

    private function checkMigrationForUnavailableLanguage(string $languageId): void
    {
        $dbData = $this->connection->fetchOne(
            'SELECT * FROM country_translation WHERE language_id = ? AND country_id = ?',
            [$languageId, $this->countryIdSlovakia]
        );
        $expectedHash = md5(serialize($dbData));
        $this->migration->update($this->connection);
        $dbData = $this->connection->fetchOne(
            'SELECT * FROM country_translation WHERE language_id = ? AND country_id = ?',
            [$languageId, $this->countryIdSlovakia]
        );
        $actualHash = md5(serialize($dbData));
        //language not available just makes the migration not find the language. actual should be what has been set by data
        static::assertEquals(
            $expectedHash,
            $actualHash
        );
    }

    /**
     * @param array<string, bool|string> $data
     *                    SlovakiaAvailable -> changes if the Migration can find the country
     *                    languageEnAvailable -> English language Available
     *                    languageDeAvailable -> German language Available
     *                    EnTranslation -> sets the name of the country in english
     *                    DeTranslation -> sets the name of the country in german
     */
    private function setDB(array $data): void
    {
        //assumes the Country is always there in our testDB, changes the iso3 to make country unavailable
        if ($data['SlovakiaAvailable'] !== null) {
            if ($data['SlovakiaAvailable'] === false) {
                $this->connection->update('country', ['iso3' => 'SV?'], ['iso3' => 'SVK']);
            } else {
                $this->connection->update('country', ['iso3' => 'SVK'], ['iso3' => 'SV?']);
            }
        }
        if ($data['languageEnAvailable'] !== null) {
            if ($data['languageEnAvailable'] === false) {
                $this->connection->update('locale', ['code' => 'en_NA'], ['code' => 'en-GB']);
            } else {
                $this->connection->update('locale', ['code' => 'de-DE'], ['code' => 'de_GB']);
            }
        }
        if ($data['languageDeAvailable'] !== null) {
            if ($data['languageDeAvailable'] === false) {
                $this->connection->update('locale', ['code' => 'de_NA'], ['code' => 'de-DE']);
            } else {
                $this->connection->update('locale', ['code' => 'de-DE'], ['code' => 'de_NA']);
            }
        }
        if ($data['EnTranslation'] !== null) {
            $this->connection->update('country_translation', ['name' => $data['EnTranslation']], ['language_id' => $this->languageEN]);
        }
        if ($data['DeTranslation'] !== null) {
            $this->connection->update('country_translation', ['name' => $data['DeTranslation']], ['language_id' => $this->languageDE]);
        }
    }
}
