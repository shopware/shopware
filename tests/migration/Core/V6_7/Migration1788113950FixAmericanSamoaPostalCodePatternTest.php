<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1788113950FixAmericanSamoaPostalCodePattern;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(Migration1788113950FixAmericanSamoaPostalCodePattern::class)]
class Migration1788113950FixAmericanSamoaPostalCodePatternTest extends TestCase
{
    use MigrationTestTrait;

    private const BROKEN_PATTERN = '(96799)(?  :[ \\-](\\d{4}))?';

    private const FIXED_PATTERN = '(96799)(?:[ \\-](\\d{4}))?';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788113950, (new Migration1788113950FixAmericanSamoaPostalCodePattern())->getCreationTimestamp());
    }

    public function testUpdateReplacesTheBrokenPattern(): void
    {
        $countryId = $this->givenCountryWithPattern(self::BROKEN_PATTERN);

        $migration = new Migration1788113950FixAmericanSamoaPostalCodePattern();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(self::FIXED_PATTERN, $this->getPattern($countryId));
    }

    public function testUpdateKeepsACustomizedPattern(): void
    {
        $customPattern = 'CUSTOM-\\d{3}';
        $countryId = $this->givenCountryWithPattern($customPattern);

        (new Migration1788113950FixAmericanSamoaPostalCodePattern())->update($this->connection);

        static::assertSame($customPattern, $this->getPattern($countryId));
    }

    public function testTheFixedPatternAcceptsAmericanSamoaPostalCodes(): void
    {
        static::assertSame(1, preg_match('/^' . self::FIXED_PATTERN . '$/', '96799'));
        static::assertSame(1, preg_match('/^' . self::FIXED_PATTERN . '$/', '96799 1234'));
        static::assertSame(1, preg_match('/^' . self::FIXED_PATTERN . '$/', '96799-1234'));
        static::assertSame(0, preg_match('/^' . self::FIXED_PATTERN . '$/', 'asdasd'));
    }

    private function givenCountryWithPattern(string $pattern): string
    {
        $countryId = $this->connection->fetchOne('SELECT `id` FROM `country` LIMIT 1');
        static::assertIsString($countryId);

        $this->connection->executeStatement(
            'UPDATE `country` SET `default_postal_code_pattern` = :pattern WHERE `id` = :id',
            ['pattern' => $pattern, 'id' => $countryId]
        );

        return $countryId;
    }

    private function getPattern(string $countryId): string
    {
        $pattern = $this->connection->fetchOne('SELECT `default_postal_code_pattern` FROM `country` WHERE `id` = :id', ['id' => $countryId]);
        static::assertIsString($pattern);

        return $pattern;
    }
}
