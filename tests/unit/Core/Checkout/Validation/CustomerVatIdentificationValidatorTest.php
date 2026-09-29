<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentificationValidator;
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
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerVatIdentificationValidator::class)]
class CustomerVatIdentificationValidatorTest extends TestCase
{
    private const DE_ID = '0199f1c4b0d3736a9f3d0f2c5a1b0de0';

    private const EU_PATTERNS = [
        ['iso' => 'BE', 'id' => '0199f1c4b0d3736a9f3d0f2c5a1b0be0', 'vatIdPattern' => 'BE\d{10}'],
        ['iso' => 'DE', 'id' => self::DE_ID, 'vatIdPattern' => 'DE\d{9}'],
        ['iso' => 'NL', 'id' => '0199f1c4b0d3736a9f3d0f2c5a1b0140', 'vatIdPattern' => 'NL\d{9}B\d{2}'],
    ];

    private ExecutionContextInterface&MockObject $context;

    protected function setUp(): void
    {
        $this->context = $this->createMock(ExecutionContextInterface::class);
    }

    public function testCaseSensitivityOfPattern(): void
    {
        $validator = $this->createValidator('[A-Z]+');

        $this->expectSingleViolationFor('abc');

        $validator->validate(['abc'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testVatIdMatchingTheCountryPatternIsAccepted(): void
    {
        $validator = $this->createValidator('DE\\d{9}');

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['DE123456789'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testTheEuPatternsAreNotLoadedWhenTheCountryPatternAlreadyMatches(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        // Every VAT ID matches the country's own pattern, so the fallback must stay off the hot path
        $repository->expects($this->once())->method('search')->willReturnCallback(
            static function (Criteria $criteria, Context $context): EntitySearchResult {
                static::assertNotSame([], $criteria->getIds(), 'The EU patterns must not be loaded');

                return self::searchResult($criteria, $context, [], self::createCountry((string) $criteria->getIds()[0], 'DE\\d{9}'));
            }
        );

        $validator = new CustomerVatIdentificationValidator(new VatIdPatternProvider($repository, static::createStub(SystemConfigService::class)));
        $validator->initialize($this->context);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['DE123456789', 'DE987654321'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testVatIdOfAnotherEuMemberStateIsAccepted(): void
    {
        $validator = $this->createValidator('BE\\d{10}', self::EU_PATTERNS);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['NL123456789B01'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testNonEuVatIdIsRejected(): void
    {
        $validator = $this->createValidator('BE\\d{10}', self::EU_PATTERNS);

        $this->expectSingleViolationFor('CHE123456789');

        $validator->validate(['CHE123456789'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testACountryOutsideTheEuOnlyAcceptsItsOwnPattern(): void
    {
        // There is no union outside the EU whose VAT IDs a country would have to honour
        $validator = $this->createValidator('CHE\\d{9}', self::EU_PATTERNS, isEu: false);

        $this->expectSingleViolationFor('NL123456789B01');

        $validator->validate(['NL123456789B01'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testACountryOutsideTheEuAcceptsAVatIdOfItsOwn(): void
    {
        $validator = $this->createValidator('CHE\\d{9}', self::EU_PATTERNS, isEu: false);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['CHE123456789'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testOnlyTheUnmatchedVatIdOfASetIsReported(): void
    {
        $validator = $this->createValidator('BE\\d{10}', self::EU_PATTERNS);

        $this->expectSingleViolationFor('INVALID');

        $validator->validate(['NL123456789B01', 'INVALID'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testTheCountrySwitchTurnsTheCheckOff(): void
    {
        $validator = $this->createValidator('BE\\d{10}', self::EU_PATTERNS, checkVatIdPattern: false);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['INVALID'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: false,
        ));
    }

    public function testNothingIsValidatedWithoutACountryPattern(): void
    {
        $validator = $this->createValidator('', self::EU_PATTERNS);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['INVALID'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testUnknownCountryIsNotValidated(): void
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            static fn (Criteria $criteria, Context $context) => self::searchResult($criteria, $context, self::EU_PATTERNS, null)
        );

        $validator = new CustomerVatIdentificationValidator(new VatIdPatternProvider($repository, static::createStub(SystemConfigService::class)));
        $validator->initialize($this->context);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['INVALID'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testAVatIdOfTheSellersOwnMemberStateIsRejectedForATaxDecision(): void
    {
        // A sales channel means the caller decides about tax, so the seller's own member state is excluded
        $validator = $this->createValidator('BE\\d{10}', self::EU_PATTERNS, sellerCountryId: self::DE_ID);

        $this->expectSingleViolationFor('DE123456789');

        $validator->validate(['DE123456789'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            salesChannelId: Uuid::randomHex(),
        ));
    }

    public function testAVatIdOfTheSellersOwnMemberStateStaysValidWhenOnlyTheFormatIsChecked(): void
    {
        // Registration validates the format a customer entered, so a domestic VAT ID has to pass
        $validator = $this->createValidator('BE\\d{10}', self::EU_PATTERNS, sellerCountryId: self::DE_ID);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['DE123456789'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testAVatIdOfAnotherMemberStateStaysValidForATaxDecision(): void
    {
        $validator = $this->createValidator('BE\\d{10}', self::EU_PATTERNS, sellerCountryId: self::DE_ID);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['NL123456789B01'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            salesChannelId: Uuid::randomHex(),
        ));
    }

    public function testAVatIdOfAnotherMemberStateIsRejectedForATaxDecisionWhileNoSellerCountryIsConfigured(): void
    {
        // A domestic supply is indistinguishable from an intra-community one, so the fallback stays off
        $validator = $this->createValidator('BE\\d{10}', self::EU_PATTERNS);

        $this->expectSingleViolationFor('NL123456789B01');

        $validator->validate(['NL123456789B01'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            salesChannelId: Uuid::randomHex(),
        ));
    }

    /**
     * @param list<array{iso: string, id: string, vatIdPattern: string}> $euPatterns
     */
    private function createValidator(
        string $countryPattern,
        array $euPatterns = [],
        bool $checkVatIdPattern = true,
        bool $isEu = true,
        ?string $sellerCountryId = null,
    ): CustomerVatIdentificationValidator {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            static fn (Criteria $criteria, Context $context) => self::searchResult(
                $criteria,
                $context,
                $euPatterns,
                $criteria->getIds() === [] ? null : self::createCountry((string) $criteria->getIds()[0], $countryPattern, $isEu, $checkVatIdPattern),
            )
        );

        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getString')->willReturn($sellerCountryId ?? '');

        $validator = new CustomerVatIdentificationValidator(new VatIdPatternProvider($repository, $systemConfigService));
        $validator->initialize($this->context);

        return $validator;
    }

    private function expectSingleViolationFor(string $vatId): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder
            ->expects($this->once())
            ->method('setParameter')
            ->willReturnCallback(function (string $key, string $value) use ($builder, $vatId): ConstraintViolationBuilderInterface {
                static::assertSame('{{ vatId }}', $key);
                static::assertSame('"' . $vatId . '"', $value);

                return $builder;
            });

        $builder
            ->expects($this->once())
            ->method('setCode')
            ->willReturnSelf();

        $builder
            ->expects($this->once())
            ->method('addViolation');

        $this->context
            ->expects($this->once())
            ->method('buildViolation')
            ->willReturn($builder);
    }

    /**
     * @param list<array{iso: string, id: string, vatIdPattern: string}> $euPatterns
     *
     * @return EntitySearchResult<CountryCollection>
     */
    private static function searchResult(Criteria $criteria, Context $context, array $euPatterns, ?CountryEntity $country): EntitySearchResult
    {
        $countries = new CountryCollection();

        if ($criteria->getIds() === []) {
            foreach ($euPatterns as $row) {
                $euCountry = self::createCountry($row['id'], $row['vatIdPattern']);
                $euCountry->setIso($row['iso']);
                $countries->add($euCountry);
            }
        } elseif ($country !== null) {
            $countries->add($country);
        }

        return new EntitySearchResult(CountryDefinition::ENTITY_NAME, $countries->count(), $countries, null, $criteria, $context);
    }

    private static function createCountry(string $id, ?string $vatIdPattern, bool $isEu = true, bool $checkVatIdPattern = true): CountryEntity
    {
        $country = new CountryEntity();
        $country->setId($id);
        $country->setVatIdPattern($vatIdPattern);
        $country->setIsEu($isEu);
        $country->setCheckVatIdPattern($checkVatIdPattern);

        return $country;
    }
}
