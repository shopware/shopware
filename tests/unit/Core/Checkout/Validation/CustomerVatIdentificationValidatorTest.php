<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Validation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentificationValidator;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
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
    private const EU_PATTERNS = [
        ['iso' => 'BE', 'vat_id_pattern' => 'BE\d{10}'],
        ['iso' => 'NL', 'vat_id_pattern' => 'NL\d{9}B\d{2}'],
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

    public function testEuPatternsAreNotConsultedWhenFlagIsOff(): void
    {
        $validator = $this->createValidator('DE\d{9}', self::EU_PATTERNS);

        $this->expectSingleViolationFor('NL123456789B01');

        $validator->validate(['NL123456789B01'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testVatIdMatchingTheCountryPatternIsAcceptedWhenFlagIsOn(): void
    {
        $validator = $this->createValidator('DE\d{9}');

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['DE123456789'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            matchesAnyEuVat: true,
        ));
    }

    public function testVatIdOfAnotherEuMemberStateIsAcceptedWhenFlagIsOn(): void
    {
        $validator = $this->createValidator('BE\d{10}', self::EU_PATTERNS);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['NL123456789B01'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            matchesAnyEuVat: true,
        ));
    }

    public function testNonEuVatIdIsRejectedWhenFlagIsOn(): void
    {
        $validator = $this->createValidator('BE\d{10}', self::EU_PATTERNS);

        $this->expectSingleViolationFor('CHE123456789');

        $validator->validate(['CHE123456789'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            matchesAnyEuVat: true,
        ));
    }

    public function testOnlyTheUnmatchedVatIdOfASetIsReported(): void
    {
        $validator = $this->createValidator('BE\d{10}', self::EU_PATTERNS);

        $this->expectSingleViolationFor('INVALID');

        $validator->validate(['NL123456789B01', 'INVALID'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            matchesAnyEuVat: true,
        ));
    }

    public function testCountrySwitchWinsOverFlag(): void
    {
        $validator = $this->createValidator('BE\d{10}', self::EU_PATTERNS, checkVatIdPattern: false);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['INVALID'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: false,
            matchesAnyEuVat: true,
        ));
    }

    public function testNothingIsValidatedWithoutCountryPatternWhenFlagIsOff(): void
    {
        $validator = $this->createValidator('', self::EU_PATTERNS);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['INVALID'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
        ));
    }

    public function testCountryWithoutOwnPatternFallsBackToTheEuPatterns(): void
    {
        $validator = $this->createValidator('', self::EU_PATTERNS);

        $this->expectSingleViolationFor('INVALID');

        $validator->validate(['NL123456789B01', 'INVALID'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            matchesAnyEuVat: true,
        ));
    }

    public function testUnknownCountryIsNotValidated(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->method('fetchAllAssociative')->willReturn(self::EU_PATTERNS);

        $validator = new CustomerVatIdentificationValidator(new VatIdPatternProvider($connection));
        $validator->initialize($this->context);

        $this->context->expects($this->never())->method('buildViolation');

        $validator->validate(['INVALID'], new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            matchesAnyEuVat: true,
        ));
    }

    /**
     * @param list<array{iso: string, vat_id_pattern: string}> $euPatterns
     */
    private function createValidator(
        string $countryPattern,
        array $euPatterns = [],
        bool $checkVatIdPattern = true,
    ): CustomerVatIdentificationValidator {
        $connection = static::createStub(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'check_vat_id_pattern' => (int) $checkVatIdPattern,
                'vat_id_pattern' => $countryPattern,
            ]);
        $connection->method('fetchAllAssociative')->willReturn($euPatterns);

        $validator = new CustomerVatIdentificationValidator(new VatIdPatternProvider($connection));
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
}
