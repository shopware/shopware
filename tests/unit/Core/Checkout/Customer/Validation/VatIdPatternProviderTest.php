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
    private const BE_ID = '0199f1c4b0d3736a9f3d0f2c5a1b0be0';

    private const NL_ID = '0199f1c4b0d3736a9f3d0f2c5a1b0140';

    public function testReturnsThePatternsKeyedByTheirCountry(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vat_id_pattern' => 'BE\d{10}'],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(
            ['BE' => 'BE\d{10}', 'NL' => 'NL\d{9}B\d{2}'],
            $provider->getEuPatterns(),
        );
    }

    public function testDropsPatternsThatDoNotCompile(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vat_id_pattern' => 'BE[0-9'],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(['NL' => 'NL\d{9}B\d{2}'], $provider->getEuPatterns());
    }

    public function testDropsPatternsThatBreakOutOfTheDelimiters(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vat_id_pattern' => 'BE/i'],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(['NL' => 'NL\d{9}B\d{2}'], $provider->getEuPatterns());
    }

    public function testGetStateByEuVatIdReturnsTheMemberStateItBelongsTo(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vat_id_pattern' => 'BE\d{10}'],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame('NL', $provider->getStateByEuVatId('NL123456789B01'));
    }

    public function testGetStateByEuVatIdReturnsNullForANonEuVatId(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vat_id_pattern' => 'BE\d{10}'],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertNull($provider->getStateByEuVatId('CHE123456789'));
    }

    public function testGetStateByEuVatIdReturnsNullWhenNoCountryHasAPattern(): void
    {
        $provider = $this->createProvider([]);

        static::assertNull($provider->getStateByEuVatId('NL123456789B01'));
    }

    public function testTheCountryOfAVatIdListIsTheStateOfItsFirstEntry(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vat_id_pattern' => 'BE\d{10}'],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        // The storefront exposes one input, so a second entry can only come from the API and must not
        // silently win over the first
        static::assertSame(self::NL_ID, $provider->getCountryIdForVatIds(['NL123456789B01', 'BE0123456789']));
    }

    public function testTheCountryOfAVatIdListSkipsEmptyEntries(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'NL', 'id' => self::NL_ID, 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(self::NL_ID, $provider->getCountryIdForVatIds(['', 'NL123456789B01']));
    }

    public function testAVatIdOfNoMemberStateHasNoCountry(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'NL', 'id' => self::NL_ID, 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertNull($provider->getCountryIdForVatIds(['CHE123456789']));
    }

    public function testAnEmptyVatIdListHasNoCountry(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'NL', 'id' => self::NL_ID, 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertNull($provider->getCountryIdForVatIds([]));
        static::assertNull($provider->getCountryIdForVatIds(null));
    }

    public function testTheEuPatternsAreReadOnce(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([['iso' => 'NL', 'id' => self::NL_ID, 'vat_id_pattern' => 'NL\d{9}B\d{2}']]);

        $provider = new VatIdPatternProvider($connection);

        static::assertSame('NL', $provider->getStateByEuVatId('NL123456789B01'));
        static::assertSame('NL', $provider->getStateByEuVatId('NL987654321B02'));
        static::assertSame(['NL' => 'NL\d{9}B\d{2}'], $provider->getEuPatterns());
    }

    public function testResetMakesTheEuPatternsBeReadAgain(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('fetchAllAssociative')
            ->willReturn([['iso' => 'NL', 'id' => self::NL_ID, 'vat_id_pattern' => 'NL\d{9}B\d{2}']]);

        $provider = new VatIdPatternProvider($connection);

        $provider->getEuPatterns();
        $provider->reset();

        static::assertSame(['NL' => 'NL\d{9}B\d{2}'], $provider->getEuPatterns());
    }

    public function testTheCountrySettingsAreReadOncePerCountry(): void
    {
        $countryId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        // The route builds the validation definition and the validator checks the VAT IDs against the
        // same country moments later, so the settings must not be fetched twice per request
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['is_eu' => 1, 'check_vat_id_pattern' => 1, 'vat_id_pattern' => 'NL\\d{9}B\\d{2}']);

        $provider = new VatIdPatternProvider($connection);

        static::assertSame($provider->getCountrySettings($countryId), $provider->getCountrySettings($countryId));
    }

    public function testAnUnknownCountryIsNotLookedUpAgain(): void
    {
        $countryId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAssociative')->willReturn(false);

        $provider = new VatIdPatternProvider($connection);

        static::assertNull($provider->getCountrySettings($countryId));
        static::assertNull($provider->getCountrySettings($countryId));
    }

    public function testResetMakesTheCountrySettingsBeReadAgain(): void
    {
        $countryId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('fetchAssociative')
            ->willReturn(['is_eu' => 1, 'check_vat_id_pattern' => 1, 'vat_id_pattern' => 'NL\\d{9}B\\d{2}']);

        $provider = new VatIdPatternProvider($connection);

        $provider->getCountrySettings($countryId);
        $provider->reset();

        static::assertSame(
            ['isEu' => true, 'checkPattern' => true, 'pattern' => 'NL\\d{9}B\\d{2}'],
            $provider->getCountrySettings($countryId),
        );
    }

    public function testTheCountrySettingsAreCachedPerCountry(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('fetchAssociative')
            ->willReturn(['is_eu' => 1, 'check_vat_id_pattern' => 1, 'vat_id_pattern' => 'NL\\d{9}B\\d{2}']);

        $provider = new VatIdPatternProvider($connection);

        $provider->getCountrySettings(Uuid::randomHex());
        $provider->getCountrySettings(Uuid::randomHex());
    }

    public function testCountrySettingsReportThePatternAndTheSwitch(): void
    {
        $provider = $this->createProviderForCountry([
            'is_eu' => 1,
            'check_vat_id_pattern' => 1,
            'vat_id_pattern' => 'BE\d{10}',
        ]);

        static::assertSame(
            ['isEu' => true, 'checkPattern' => true, 'pattern' => 'BE\d{10}'],
            $provider->getCountrySettings(Uuid::randomHex()),
        );
    }

    public function testCountrySettingsReportACountryOutsideTheEu(): void
    {
        $provider = $this->createProviderForCountry([
            'is_eu' => 0,
            'check_vat_id_pattern' => 1,
            'vat_id_pattern' => 'CHE\d{9}',
        ]);

        static::assertSame(
            ['isEu' => false, 'checkPattern' => true, 'pattern' => 'CHE\d{9}'],
            $provider->getCountrySettings(Uuid::randomHex()),
        );
    }

    public function testCountrySettingsReportADisabledSwitch(): void
    {
        $provider = $this->createProviderForCountry([
            'is_eu' => 1,
            'check_vat_id_pattern' => 0,
            'vat_id_pattern' => 'BE\d{10}',
        ]);

        static::assertSame(
            ['isEu' => true, 'checkPattern' => false, 'pattern' => 'BE\d{10}'],
            $provider->getCountrySettings(Uuid::randomHex()),
        );
    }

    public function testCountrySettingsReportAnEmptyPatternAsNone(): void
    {
        $provider = $this->createProviderForCountry([
            'is_eu' => 1,
            'check_vat_id_pattern' => 1,
            'vat_id_pattern' => '',
        ]);

        static::assertSame(
            ['isEu' => true, 'checkPattern' => true, 'pattern' => null],
            $provider->getCountrySettings(Uuid::randomHex()),
        );
    }

    public function testCountrySettingsReportAMissingPatternAsNone(): void
    {
        $provider = $this->createProviderForCountry([
            'is_eu' => 1,
            'check_vat_id_pattern' => 1,
            'vat_id_pattern' => null,
        ]);

        static::assertSame(
            ['isEu' => true, 'checkPattern' => true, 'pattern' => null],
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

        yield 'a pattern that does not compile matches nothing' => ['BE[0-9', 'BE0123456789', false];

        yield 'a pattern that breaks out of the delimiters matches nothing' => ['BE/i', 'BE0123456789', false];
    }

    /**
     * @param list<array{iso: string, id: string, vat_id_pattern: string}> $rows
     */
    private function createProvider(array $rows): VatIdPatternProvider
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        return new VatIdPatternProvider($connection);
    }

    /**
     * @param array{is_eu: int, check_vat_id_pattern: int, vat_id_pattern: string|null}|false $country
     */
    private function createProviderForCountry(array|false $country): VatIdPatternProvider
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($country);

        return new VatIdPatternProvider($connection);
    }
}
