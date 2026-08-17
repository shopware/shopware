<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Validation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentificationValidator;
use Shopware\Core\Checkout\Customer\Validation\EuVatIdPatternProvider;
use Shopware\Core\Checkout\Customer\Validation\VatIdPattern;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerVatIdentificationValidator::class)]
class CustomerVatIdentificationValidatorTest extends TestCase
{
    private ExecutionContextInterface&MockObject $context;

    protected function setUp(): void
    {
        $this->context = $this->createMock(ExecutionContextInterface::class);
    }

    public function testCaseSensitivityOfPattern(): void
    {
        $validator = $this->createValidator('[A-Z]+', $this->createEuPatternProviderMatching([]));

        $this->expectSingleViolationFor('abc');

        $validator->validate(['abc'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testEuPatternsAreNotConsultedWhenFlagIsOff(): void
    {
        $euPatterns = $this->createMock(EuVatIdPatternProvider::class);
        $euPatterns->expects($this->never())->method('matchVatId');

        $validator = $this->createValidator('DE\d{9}', $euPatterns);

        $this->expectSingleViolationFor('NL123456789B01');

        $validator->validate(['NL123456789B01'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testVatIdMatchingTheCountryPatternSkipsTheEuPatterns(): void
    {
        $euPatterns = $this->createMock(EuVatIdPatternProvider::class);
        $euPatterns->expects($this->never())->method('matchVatId');

        $validator = $this->createValidator('DE\d{9}', $euPatterns);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['DE123456789'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            anyEuCountry: true,
        ));
    }

    public function testVatIdOfAnotherEuMemberStateIsAcceptedWhenFlagIsOn(): void
    {
        $validator = $this->createValidator('BE\d{10}', $this->createEuPatternProviderMatching(['NL123456789B01']));

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['NL123456789B01'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            anyEuCountry: true,
        ));
    }

    public function testNonEuVatIdIsRejectedWhenFlagIsOn(): void
    {
        $validator = $this->createValidator('BE\d{10}', $this->createEuPatternProviderMatching([]));

        $this->expectSingleViolationFor('CHE123456789');

        $validator->validate(['CHE123456789'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            anyEuCountry: true,
        ));
    }

    public function testOnlyTheUnmatchedVatIdOfASetIsReported(): void
    {
        $validator = $this->createValidator('BE\d{10}', $this->createEuPatternProviderMatching(['NL123456789B01']));

        $this->expectSingleViolationFor('INVALID');

        $validator->validate(['NL123456789B01', 'INVALID'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            anyEuCountry: true,
        ));
    }

    public function testCountrySwitchWinsOverFlag(): void
    {
        $euPatterns = $this->createMock(EuVatIdPatternProvider::class);
        $euPatterns->expects($this->never())->method('matchVatId');

        $validator = $this->createValidator('BE\d{10}', $euPatterns, checkVatIdPattern: false);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['INVALID'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: false,
            anyEuCountry: true,
        ));
    }

    public function testNothingIsValidatedWithoutCountryPatternWhenFlagIsOff(): void
    {
        $validator = $this->createValidator('', $this->createEuPatternProviderMatching([]));

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['INVALID'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testCountryWithoutOwnPatternFallsBackToTheEuPatterns(): void
    {
        $validator = $this->createValidator('', $this->createEuPatternProviderMatching(['NL123456789B01']));

        $this->expectSingleViolationFor('INVALID');

        $validator->validate(['NL123456789B01', 'INVALID'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            anyEuCountry: true,
        ));
    }

    private function createValidator(
        string $countryPattern,
        EuVatIdPatternProvider $euPatterns,
        bool $checkVatIdPattern = true,
    ): CustomerVatIdentificationValidator {
        $connection = static::createStub(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'iso' => 'BE',
                'check_vat_id_pattern' => (int) $checkVatIdPattern,
                'vat_id_pattern' => $countryPattern,
            ]);

        $validator = new CustomerVatIdentificationValidator($connection, $euPatterns);
        $validator->initialize($this->context);

        return $validator;
    }

    /**
     * @param list<string> $matchingVatIds
     */
    private function createEuPatternProviderMatching(array $matchingVatIds): EuVatIdPatternProvider&Stub
    {
        $euPatterns = static::createStub(EuVatIdPatternProvider::class);
        $euPatterns->method('matchVatId')->willReturnCallback(
            static fn (string $vatId): ?VatIdPattern => \in_array($vatId, $matchingVatIds, true)
                ? new VatIdPattern('NL', 'NL\d{9}B\d{2}')
                : null
        );

        return $euPatterns;
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
}
