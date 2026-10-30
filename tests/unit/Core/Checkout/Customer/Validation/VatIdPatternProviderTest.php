<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(VatIdPatternProvider::class)]
class VatIdPatternProviderTest extends TestCase
{
    private const BE_ID = '0199f1c4b0d3736a9f3d0f2c5a1b0be0';

    private const NL_ID = '0199f1c4b0d3736a9f3d0f2c5a1b0140';

    private const GR_ID = '0199f1c4b0d3736a9f3d0f2c5a1b0947';

    public function testReturnsThePatternsKeyedByTheirCountry(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vatIdPattern' => 'BE\d{10}'],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(
            ['BE' => 'BE\d{10}', 'NL' => 'NL\d{9}B\d{2}'],
            $provider->getEuPatterns(),
        );
    }

    public function testDropsPatternsThatDoNotCompile(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vatIdPattern' => 'BE[0-9'],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(['NL' => 'NL\d{9}B\d{2}'], $provider->getEuPatterns());
    }

    public function testDropsMemberStatesWithoutAPattern(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vatIdPattern' => null],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(['NL' => 'NL\d{9}B\d{2}'], $provider->getEuPatterns());
    }

    public function testDropsMemberStatesWithoutAnIsoCode(): void
    {
        $provider = $this->createProvider([
            ['iso' => null, 'id' => self::BE_ID, 'vatIdPattern' => 'BE\d{10}'],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(['NL' => 'NL\d{9}B\d{2}'], $provider->getEuPatterns());
    }

    public function testDropsPatternsThatBreakOutOfTheDelimiters(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vatIdPattern' => 'BE/i'],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(['NL' => 'NL\d{9}B\d{2}'], $provider->getEuPatterns());
    }

    public function testAVatIdHasNoCountryWhenNoCountryHasAPattern(): void
    {
        $provider = $this->createProvider([]);

        static::assertNull($provider->getCountryIdForVatIds(['NL123456789B01']));
    }

    public function testTheCountryOfAVatIdListIsTheStateOfItsFirstEntry(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vatIdPattern' => 'BE\d{10}'],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\d{9}B\d{2}'],
        ]);

        // The stored VAT country describes the first entry, independently of the tax decision
        static::assertSame(self::NL_ID, $provider->getCountryIdForVatIds(['NL123456789B01', 'BE0123456789']));
    }

    public function testTheCountryOfAVatIdIsTheMemberStateItNames(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vatIdPattern' => '[A-Z0-9]+'],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(self::NL_ID, $provider->getCountryIdForVatIds(['NL123456789B01']));
    }

    public function testTheCountryOfAVatIdWithoutAMemberStateOfItsPrefixIsTheMatchingOne(): void
    {
        // The shipped Greek pattern also accepts the EL prefix, which is the ISO code of no country
        $provider = $this->createProvider([
            ['iso' => 'GR', 'id' => self::GR_ID, 'vatIdPattern' => '(EL|GR)\d{9}'],
        ]);

        static::assertSame(self::GR_ID, $provider->getCountryIdForVatIds(['EL123456789']));
    }

    public function testTheCountryOfAVatIdListSkipsEmptyEntries(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertSame(self::NL_ID, $provider->getCountryIdForVatIds(['', 'NL123456789B01']));
    }

    public function testAVatIdOfNoMemberStateHasNoCountry(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertNull($provider->getCountryIdForVatIds(['CHE123456789']));
    }

    public function testAnEmptyVatIdListHasNoCountry(): void
    {
        $provider = $this->createProvider([
            ['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\d{9}B\d{2}'],
        ]);

        static::assertNull($provider->getCountryIdForVatIds([]));
        static::assertNull($provider->getCountryIdForVatIds(null));
    }

    public function testTheEuPatternsAreReadOnce(): void
    {
        $repository = $this->createCountryRepositoryExpecting(
            $this->once(),
            [['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\d{9}B\d{2}']],
        );

        $provider = new VatIdPatternProvider($repository);

        static::assertSame(self::NL_ID, $provider->getCountryIdForVatIds(['NL123456789B01']));
        static::assertSame(self::NL_ID, $provider->getCountryIdForVatIds(['NL987654321B02']));
        static::assertSame(['NL' => 'NL\d{9}B\d{2}'], $provider->getEuPatterns());
    }

    public function testResetMakesTheEuPatternsBeReadAgain(): void
    {
        $repository = $this->createCountryRepositoryExpecting(
            $this->exactly(2),
            [['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\d{9}B\d{2}']],
        );

        $provider = new VatIdPatternProvider($repository);

        $provider->getEuPatterns();
        $provider->reset();

        static::assertSame(['NL' => 'NL\d{9}B\d{2}'], $provider->getEuPatterns());
    }

    public function testTheCountrySettingsAreReadOncePerCountry(): void
    {
        $countryId = Uuid::randomHex();

        // The route and the validator hit the same country within a request
        $repository = $this->createCountryRepositoryExpecting(
            $this->once(),
            country: ['isEu' => true, 'checkVatIdPattern' => true, 'vatIdPattern' => 'NL\\d{9}B\\d{2}'],
        );

        $provider = new VatIdPatternProvider($repository);

        static::assertSame($provider->getCountrySettings($countryId), $provider->getCountrySettings($countryId));
    }

    public function testAnUnknownCountryIsNotLookedUpAgain(): void
    {
        $countryId = Uuid::randomHex();

        $repository = $this->createCountryRepositoryExpecting($this->once());

        $provider = new VatIdPatternProvider($repository);

        static::assertNull($provider->getCountrySettings($countryId));
        static::assertNull($provider->getCountrySettings($countryId));
    }

    public function testResetMakesTheCountrySettingsBeReadAgain(): void
    {
        $countryId = Uuid::randomHex();

        $repository = $this->createCountryRepositoryExpecting(
            $this->exactly(2),
            country: ['isEu' => true, 'checkVatIdPattern' => true, 'vatIdPattern' => 'NL\\d{9}B\\d{2}'],
        );

        $provider = new VatIdPatternProvider($repository);

        $provider->getCountrySettings($countryId);
        $provider->reset();

        static::assertSame(
            ['isEu' => true, 'checkPattern' => true, 'pattern' => 'NL\\d{9}B\\d{2}'],
            $provider->getCountrySettings($countryId),
        );
    }

    public function testTheCountrySettingsAreCachedPerCountry(): void
    {
        $repository = $this->createCountryRepositoryExpecting(
            $this->exactly(2),
            country: ['isEu' => true, 'checkVatIdPattern' => true, 'vatIdPattern' => 'NL\\d{9}B\\d{2}'],
        );

        $provider = new VatIdPatternProvider($repository);

        $provider->getCountrySettings(Uuid::randomHex());
        $provider->getCountrySettings(Uuid::randomHex());
    }

    public function testCountrySettingsReportThePatternAndTheSwitch(): void
    {
        $provider = $this->createProviderForCountry([
            'isEu' => true,
            'checkVatIdPattern' => true,
            'vatIdPattern' => 'BE\d{10}',
        ]);

        static::assertSame(
            ['isEu' => true, 'checkPattern' => true, 'pattern' => 'BE\d{10}'],
            $provider->getCountrySettings(Uuid::randomHex()),
        );
    }

    public function testCountrySettingsReportACountryOutsideTheEu(): void
    {
        $provider = $this->createProviderForCountry([
            'isEu' => false,
            'checkVatIdPattern' => true,
            'vatIdPattern' => 'CHE\d{9}',
        ]);

        static::assertSame(
            ['isEu' => false, 'checkPattern' => true, 'pattern' => 'CHE\d{9}'],
            $provider->getCountrySettings(Uuid::randomHex()),
        );
    }

    public function testCountrySettingsReportADisabledSwitch(): void
    {
        $provider = $this->createProviderForCountry([
            'isEu' => true,
            'checkVatIdPattern' => false,
            'vatIdPattern' => 'BE\d{10}',
        ]);

        static::assertSame(
            ['isEu' => true, 'checkPattern' => false, 'pattern' => 'BE\d{10}'],
            $provider->getCountrySettings(Uuid::randomHex()),
        );
    }

    public function testCountrySettingsReportAnEmptyPatternAsNone(): void
    {
        $provider = $this->createProviderForCountry([
            'isEu' => true,
            'checkVatIdPattern' => true,
            'vatIdPattern' => '',
        ]);

        static::assertSame(
            ['isEu' => true, 'checkPattern' => true, 'pattern' => null],
            $provider->getCountrySettings(Uuid::randomHex()),
        );
    }

    public function testCountrySettingsReportAMissingPatternAsNone(): void
    {
        $provider = $this->createProviderForCountry([
            'isEu' => true,
            'checkVatIdPattern' => true,
            'vatIdPattern' => null,
        ]);

        static::assertSame(
            ['isEu' => true, 'checkPattern' => true, 'pattern' => null],
            $provider->getCountrySettings(Uuid::randomHex()),
        );
    }

    public function testCountrySettingsAreNullForAnUnknownCountry(): void
    {
        $provider = $this->createProviderForCountry(null);

        static::assertNull($provider->getCountrySettings(Uuid::randomHex()));
    }

    public function testCountrySettingsAreNullForAnInvalidCountryIdWithoutLoadingAnyCountry(): void
    {
        // An empty id would be dropped from the criteria and load an arbitrary country
        $repository = $this->createCountryRepositoryExpecting(
            $this->never(),
            country: ['isEu' => true, 'checkVatIdPattern' => true, 'vatIdPattern' => 'BE\d{10}'],
        );

        $provider = new VatIdPatternProvider($repository);

        static::assertNull($provider->getCountrySettings(''));
        static::assertNull($provider->getCountrySettings('not-a-uuid'));
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

        yield 'the pattern is anchored, so a trailing newline is rejected' => ['NL\d{9}B\d{2}', "NL123456789B01\n", false];

        yield 'an alternation stays anchored on both sides' => ['DE\d{9}|AT\d{8}', 'DE123456789X', false];

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

    public function testACountryAcceptsAVatIdOfItsOwnPattern(): void
    {
        $provider = $this->createMemberStatesProvider();

        static::assertTrue($provider->acceptsVatId('BE0123456789', 'BE\\d{10}', true));
        static::assertTrue($provider->acceptsVatId('CHE123456789', 'CHE\\d{9}', false));
    }

    public function testACountryOutsideTheEuAcceptsNothingButItsOwnPattern(): void
    {
        static::assertFalse($this->createMemberStatesProvider()->acceptsVatId('BE0123456789', 'CHE\\d{9}', false));
    }

    public function testAMemberStateAcceptsTheVatIdOfAnotherMemberState(): void
    {
        static::assertTrue($this->createMemberStatesProvider()->acceptsVatId('BE0123456789', 'NL\\d{9}B\\d{2}', true));
    }

    public function testAMemberStateRejectsAVatIdOfNoMemberState(): void
    {
        static::assertFalse($this->createMemberStatesProvider()->acceptsVatId('CHE123456789', 'NL\\d{9}B\\d{2}', true));
    }

    public function testTheMemberStateOfACountryIsItsIsoCode(): void
    {
        $provider = $this->createMemberStatesProvider();

        static::assertSame('NL', $provider->getMemberStateOfCountry(self::NL_ID));
        static::assertNull($provider->getMemberStateOfCountry(Uuid::randomHex()));
    }

    public function testACountryWithoutAUsablePatternIsStillAMemberState(): void
    {
        // Emptying or breaking the pattern of the shop's own country must not turn its exclusion off
        $provider = $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vatIdPattern' => ''],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\\d{9}B\\d{2}'],
        ]);

        static::assertSame(['NL' => 'NL\\d{9}B\\d{2}'], $provider->getEuPatterns());
        static::assertSame('BE', $provider->getMemberStateOfCountry(self::BE_ID));
    }

    private function createMemberStatesProvider(): VatIdPatternProvider
    {
        return $this->createProvider([
            ['iso' => 'BE', 'id' => self::BE_ID, 'vatIdPattern' => 'BE\\d{10}'],
            ['iso' => 'NL', 'id' => self::NL_ID, 'vatIdPattern' => 'NL\\d{9}B\\d{2}'],
        ]);
    }

    /**
     * @param list<array{iso: string|null, id: string, vatIdPattern: string|null}> $euCountries
     */
    private function createProvider(array $euCountries): VatIdPatternProvider
    {
        return new VatIdPatternProvider($this->createCountryRepository($euCountries));
    }

    /**
     * @param array{isEu: bool, checkVatIdPattern: bool, vatIdPattern: string|null}|null $country
     */
    private function createProviderForCountry(?array $country): VatIdPatternProvider
    {
        return new VatIdPatternProvider($this->createCountryRepository(country: $country));
    }

    /**
     * @param list<array{iso: string|null, id: string, vatIdPattern: string|null}> $euCountries
     * @param array{isEu: bool, checkVatIdPattern: bool, vatIdPattern: string|null}|null $country
     *
     * @return EntityRepository<CountryCollection>
     */
    private function createCountryRepository(array $euCountries = [], ?array $country = null): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            static fn (Criteria $criteria, Context $context) => self::search($criteria, $context, $euCountries, $country)
        );

        return $repository;
    }

    /**
     * @param list<array{iso: string|null, id: string, vatIdPattern: string|null}> $euCountries
     * @param array{isEu: bool, checkVatIdPattern: bool, vatIdPattern: string|null}|null $country
     *
     * @return EntityRepository<CountryCollection>
     */
    private function createCountryRepositoryExpecting(InvocationOrder $invocations, array $euCountries = [], ?array $country = null): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($invocations)->method('search')->willReturnCallback(
            static fn (Criteria $criteria, Context $context) => self::search($criteria, $context, $euCountries, $country)
        );

        return $repository;
    }

    /**
     * Answers the EU country list for a criteria without ids and the requested country otherwise.
     *
     * @param list<array{iso: string|null, id: string, vatIdPattern: string|null}> $euCountries
     * @param array{isEu: bool, checkVatIdPattern: bool, vatIdPattern: string|null}|null $country
     *
     * @return EntitySearchResult<CountryCollection>
     */
    private static function search(Criteria $criteria, Context $context, array $euCountries, ?array $country): EntitySearchResult
    {
        $countries = new CountryCollection();

        if ($criteria->getIds() === []) {
            foreach ($euCountries as $row) {
                $countries->add(self::createCountry($row['id'], $row['iso'], $row['vatIdPattern'], true, true));
            }
        } elseif ($country !== null) {
            $id = $criteria->getIds()[0];
            \assert(\is_string($id));

            $countries->add(self::createCountry($id, 'XX', $country['vatIdPattern'], $country['isEu'], $country['checkVatIdPattern']));
        }

        return new EntitySearchResult(CountryDefinition::ENTITY_NAME, $countries->count(), $countries, null, $criteria, $context);
    }

    private static function createCountry(string $id, ?string $iso, ?string $vatIdPattern, bool $isEu, bool $checkVatIdPattern): CountryEntity
    {
        $country = new CountryEntity();
        $country->setId($id);
        $country->setIso($iso);
        $country->setVatIdPattern($vatIdPattern);
        $country->setIsEu($isEu);
        $country->setCheckVatIdPattern($checkVatIdPattern);

        return $country;
    }
}
