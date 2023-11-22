<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1677470540AddProvincesForCanada;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1677470540AddProvincesForCanada::class)]
class Migration1677470540AddProvincesForCanadaTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $countryId = $this->getCountryId();

        $sql = <<<'SQL'
SELECT id
FROM country_state
WHERE country_id = :countryId
SQL;

        $countryStateIds = $this->connection->fetchFirstColumn($sql, ['countryId' => $countryId]);

        foreach ($countryStateIds as $countryStateId) {
            $this->connection->delete('country_state_translation', [
                'country_state_id' => $countryStateId,
            ]);
        }

        $this->connection->delete('country_state', [
            'country_id' => $countryId,
        ]);
    }

    public function testAddCountryState(): void
    {
        $migration = new Migration1677470540AddProvincesForCanada();

        $migration->update($this->connection);

        $countryId = $this->getCountryId();

        $sql = <<<'SQL'
SELECT COUNT(id)
FROM country_state
WHERE country_id = :countryId
SQL;

        $countCountryState = $this->connection->fetchOne($sql, ['countryId' => $countryId]);

        static::assertEquals(\count(Migration1677470540AddProvincesForCanada::CANADA_STATES), $countCountryState);
    }

    public function testExecuteMigrationTwice(): void
    {
        $migration = new Migration1677470540AddProvincesForCanada();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $countryId = $this->getCountryId();

        $sql = <<<'SQL'
SELECT COUNT(id)
FROM country_state
WHERE country_id = :countryId
SQL;

        $countCountryState = $this->connection->fetchOne($sql, ['countryId' => $countryId]);

        static::assertEquals(\count(Migration1677470540AddProvincesForCanada::CANADA_STATES), $countCountryState);
    }

    public function testAddCountryStateTranslationLanguageEN(): void
    {
        $migration = new Migration1677470540AddProvincesForCanada();

        $migration->update($this->connection);

        $countryId = $this->getCountryId();
        $enLanguageId = $this->getEnLanguageId();

        $sql = <<<'SQL'
SELECT COUNT(*)
FROM country_state_translation
JOIN country_state ON country_state.id = country_state_translation.country_state_id
WHERE country_state.country_id = :countryId
AND country_state_translation.language_id = :languageId
SQL;

        $countCountryStateTranslation = $this->connection->fetchOne($sql, ['countryId' => $countryId, 'languageId' => $enLanguageId]);

        static::assertEquals(\count(Migration1677470540AddProvincesForCanada::CANADA_STATES), $countCountryStateTranslation);
    }

    public function testAddCountryStateTranslationLanguageDE(): void
    {
        $migration = new Migration1677470540AddProvincesForCanada();

        $migration->update($this->connection);

        $countryId = $this->getCountryId();
        $deLanguageId = $this->getDeLanguageId();

        $sql = <<<'SQL'
SELECT COUNT(*)
FROM country_state_translation
JOIN country_state ON country_state.id = country_state_translation.country_state_id
WHERE country_state.country_id = :countryId
AND country_state_translation.language_id = :languageId
SQL;

        $countCountryStateTranslation = $this->connection->fetchOne($sql, ['countryId' => $countryId, 'languageId' => $deLanguageId]);

        static::assertEquals(\count(Migration1677470540AddProvincesForCanada::CANADA_STATES), $countCountryStateTranslation);
    }

    private function getEnLanguageId(): string
    {
        $getLanguageSql = <<<'SQL'
            SELECT language.id
            FROM language
            JOIN locale ON locale.id = language.locale_id
            WHERE locale.code = 'en-GB'
        SQL;

        return $this->connection->fetchOne($getLanguageSql);
    }

    private function getDeLanguageId(): string
    {
        $getLanguageSql = <<<'SQL'
            SELECT language.id
            FROM language
            JOIN locale ON locale.id = language.locale_id
            WHERE locale.code = 'de-DE'
        SQL;

        return $this->connection->fetchOne($getLanguageSql);
    }

    private function getCountryId(): string
    {
        return $this->connection->fetchOne('SELECT id from country WHERE iso = \'CA\' AND iso3 = \'CAN\'');
    }
}
