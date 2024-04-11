<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1675247112ChangeCountryNamingConvention;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1675247112ChangeCountryNamingConvention::class)]
class Migration1675247112ChangeCountryNamingConventionTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    /**
     * @var array{EN: string, DE: string}
     */
    private array $languages;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $deLanguageId = $this->fetchLanguageId($this->connection, 'de-DE');
        static::assertIsString($deLanguageId);
        $enLanguageId = $this->fetchLanguageId($this->connection, 'en-GB');
        static::assertIsString($enLanguageId);

        $this->languages = [
            'EN' => $enLanguageId,
            'DE' => $deLanguageId,
        ];
    }

    #[DataProvider('dataProviderForTestChangeCountryNamingConvention')]
    public function testChangeCountryNamingConvention(string $language, string $expected): void
    {
        $migration = new Migration1675247112ChangeCountryNamingConvention();

        $migration->update($this->connection);

        $sql = <<<'SQL'
            SELECT country_translation.*
            FROM country
            JOIN country_translation ON country.id = country_translation.country_id
            WHERE country.iso = :iso
            AND country.iso3 = :iso3
            AND country_translation.language_id = :languageId
        SQL;

        /** @var array{name: string} $result */
        $result = $this->connection->fetchAssociative($sql, [
            'iso' => 'US',
            'iso3' => 'USA',
            'languageId' => $this->languages[$language],
        ]);

        static::assertEquals($result['name'], $expected);
    }

    public static function dataProviderForTestChangeCountryNamingConvention(): \Generator
    {
        yield 'Test with translation EN' => [
            'language' => 'EN',
            'expected' => 'United States of America',
        ];

        yield 'Test with translation DE' => [
            'language' => 'DE',
            'expected' => 'Vereinigte Staaten von Amerika',
        ];
    }
}
