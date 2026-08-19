<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(VatIdPatternProvider::class)]
class VatIdPatternProviderTest extends TestCase
{
    public function testReturnsThePatternsKeyedByTheirCountry(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'vat_id_pattern' => 'BE\d{10}'],
            ['iso' => 'NL', 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(
            ['BE' => 'BE\d{10}', 'NL' => 'NL\d{9}B\d{2}'],
            $provider->getEuPatterns(),
        );
    }

    public function testDropsPatternsThatDoNotCompile(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'vat_id_pattern' => 'BE[0-9'],
            ['iso' => 'NL', 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(['NL' => 'NL\d{9}B\d{2}'], $provider->getEuPatterns());
    }

    public function testDropsPatternsThatBreakOutOfTheDelimiters(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'vat_id_pattern' => 'BE/i'],
            ['iso' => 'NL', 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(['NL' => 'NL\d{9}B\d{2}'], $provider->getEuPatterns());
    }

    public function testMatchEuVatIdReturnsTheMemberStateItBelongsTo(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'vat_id_pattern' => 'BE\d{10}'],
            ['iso' => 'NL', 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame('NL', $provider->matchEuVatId('NL123456789B01'));
    }

    public function testMatchEuVatIdReturnsNullForANonEuVatId(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'vat_id_pattern' => 'BE\d{10}'],
            ['iso' => 'NL', 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertNull($provider->matchEuVatId('CHE123456789'));
    }

    public function testMatchEuVatIdReturnsNullWhenNoCountryHasAPattern(): void
    {
        $provider = $this->createProvider([]);

        static::assertNull($provider->matchEuVatId('NL123456789B01'));
    }

    public function testCountrySettingsReportThePatternAndTheSwitch(): void
    {
        $provider = $this->createProviderForCountry([
            'check_vat_id_pattern' => 1,
            'vat_id_pattern' => 'BE\d{10}',
        ]);

        static::assertSame(
            ['checkPattern' => true, 'pattern' => 'BE\d{10}'],
            $provider->getCountrySettings(Uuid::randomHex()),
        );
    }

    public function testCountrySettingsReportADisabledSwitch(): void
    {
        $provider = $this->createProviderForCountry([
            'check_vat_id_pattern' => 0,
            'vat_id_pattern' => 'BE\d{10}',
        ]);

        static::assertSame(
            ['checkPattern' => false, 'pattern' => 'BE\d{10}'],
            $provider->getCountrySettings(Uuid::randomHex()),
        );
    }

    public function testCountrySettingsReportAnEmptyPatternAsNone(): void
    {
        $provider = $this->createProviderForCountry([
            'check_vat_id_pattern' => 1,
            'vat_id_pattern' => '',
        ]);

        static::assertSame(
            ['checkPattern' => true, 'pattern' => null],
            $provider->getCountrySettings(Uuid::randomHex()),
        );
    }

    public function testCountrySettingsReportAMissingPatternAsNone(): void
    {
        $provider = $this->createProviderForCountry([
            'check_vat_id_pattern' => 1,
            'vat_id_pattern' => null,
        ]);

        static::assertSame(
            ['checkPattern' => true, 'pattern' => null],
            $provider->getCountrySettings(Uuid::randomHex()),
        );
    }

    public function testCountrySettingsAreNullForAnUnknownCountry(): void
    {
        $provider = $this->createProviderForCountry(false);

        static::assertNull($provider->getCountrySettings(Uuid::randomHex()));
    }

    #[DataProvider('matchesProvider')]
    public function testMatches(string $pattern, string $vatId, bool $expected): void
    {
        $provider = $this->createProvider([]);

        static::assertSame($expected, $provider->matches($pattern, $vatId));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function matchesProvider(): iterable
    {
        yield 'plain pattern matches its own country' => ['NL\d{9}B\d{2}', 'NL123456789B01', true];

        yield 'plain pattern rejects a foreign vat id' => ['NL\d{9}B\d{2}', 'BE0123456789', false];

        yield 'the pattern is anchored, so a trailing suffix is rejected' => ['NL\d{9}B\d{2}', 'NL123456789B01X', false];

        yield 'the pattern is anchored, so a leading prefix is rejected' => ['NL\d{9}B\d{2}', 'XNL123456789B01', false];

        yield 'the pattern is case sensitive' => ['[A-Z]+', 'abc', false];

        // The shipped Spanish pattern embeds its own anchors and only works with the wrapping anchors.
        yield 'shipped Spanish pattern accepts all three of its alternatives' => ['ES[A-Z]\d{7}[A-Z]$|^ES[A-Z][0-9]{7}[0-9A-Z]$|^ES[0-9]{8}[A-Z]', 'ESX1234567R', true];

        yield 'shipped Spanish pattern stays anchored on its last alternative' => ['ES[A-Z]\d{7}[A-Z]$|^ES[A-Z][0-9]{7}[0-9A-Z]$|^ES[0-9]{8}[A-Z]', 'ES12345678XX', false];

        yield 'shipped Irish pattern accepts the pre-2013 form' => ['IE(\d{7}[A-Z]{1,2}|(\d{1}[A-Z]{1}\d{5}[A-Z]{1}))', 'IE1B12345D', true];

        yield 'shipped Irish pattern accepts the post-2013 form' => ['IE(\d{7}[A-Z]{1,2}|(\d{1}[A-Z]{1}\d{5}[A-Z]{1}))', 'IE1234567FA', true];

        yield 'shipped Greek pattern accepts both the EL and GR prefix' => ['(EL|GR)\d{9}', 'EL123456789', true];

        yield 'shipped Romanian pattern rejects a leading zero via its lookahead' => ['RO(?!0)\d{1,10}', 'RO0123456789', false];
    }

    /**
     * @param list<array{iso: string, vat_id_pattern: string}> $rows
     */
    private function createProvider(array $rows): VatIdPatternProvider
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        return new VatIdPatternProvider($connection);
    }

    /**
     * @param array{check_vat_id_pattern: int, vat_id_pattern: string|null}|false $country
     */
    private function createProviderForCountry(array|false $country): VatIdPatternProvider
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($country);

        return new VatIdPatternProvider($connection);
    }
}
