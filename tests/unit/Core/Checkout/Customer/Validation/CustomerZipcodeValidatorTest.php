<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCodeValidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @package checkout
 *
 * @internal
 */
#[CoversClass(CustomerZipCodeValidator::class)]
class CustomerZipcodeValidatorTest extends TestCase
{
    private CustomerZipCode $constraint;

    /**
     * @var EntityRepository&MockObject
     */
    private EntityRepository $countryRepository;

    protected function setUp(): void
    {
        $this->constraint = new CustomerZipCode([
            'countryId' => Uuid::randomHex(),
        ]);

        $this->countryRepository = $this->createMock(EntityRepository::class);
    }

    public function testUnexpectedTypeException(): void
    {
        $mock = new CustomerZipCodeValidator($this->countryRepository);

        try {
            $mock->validate(['zipcode' => '1235468'], $this->createMock(Constraint::class));
        } catch (\Throwable $exception) {
            static::assertInstanceOf(UnexpectedTypeException::class, $exception);
        }
    }

    public function testValidateWithoutCountryId(): void
    {
        $this->countryRepository->expects(static::never())->method('search');

        $validator = new CustomerZipCodeValidator($this->countryRepository);

        $validator->validate(['zipcode' => '1235468'], new CustomerZipCode([]));
    }

    public function testInValidZipcodeIsRequired(): void
    {
        $countryId = $this->constraint->countryId;
        static::assertNotNull($countryId);

        $result = $this->createMock(EntitySearchResult::class);
        $country = new CountryEntity();
        $country->setIso('DE');
        $country->setId($countryId);
        $country->setPostalCodeRequired(true);
        $country->setCheckPostalCodePattern(false);
        $country->setCheckAdvancedPostalCodePattern(false);
        $country->setDefaultPostalCodePattern('\\d{5}');
        $country->setAdvancedPostalCodePattern(null);

        $result->method('get')->with($countryId)->willReturn($country);

        $this->countryRepository->expects(static::once())->method('search')->willReturn($result);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects(static::once())->method('buildViolation')->willReturnCallback(function (string $message, array $parameters = []) {
            static::assertSame($message, $this->constraint->getMessageRequired());

            $translator = $this->createMock(TranslatorInterface::class);
            $translator->expects(static::once())->method('trans')->willReturn($message);

            return new ConstraintViolationBuilder(
                new ConstraintViolationList(),
                $this->constraint,
                $message,
                $parameters,
                '',
                '',
                '',
                $translator,
            );
        });

        $mock = new CustomerZipCodeValidator($this->countryRepository);

        $mock->initialize($executionContext);

        $mock->validate('', $this->constraint);
    }

    public function testValidateWithInvalidCountryId(): void
    {
        static::expectException(CustomerException::class);

        $result = $this->createMock(EntitySearchResult::class);
        $result->expects(static::once())->method('get')->with($this->constraint->countryId)->willReturn(null);

        $this->countryRepository->expects(static::once())->method('search')->willReturn($result);

        $executionContext = $this->createMock(ExecutionContext::class);
        $mock = new CustomerZipCodeValidator($this->countryRepository);

        $mock->initialize($executionContext);

        $mock->validate('', $this->constraint);
    }

    public function testValidZipcodeIsRequired(): void
    {
        $countryId = $this->constraint->countryId;
        static::assertNotNull($countryId);

        $result = $this->createMock(EntitySearchResult::class);
        $country = new CountryEntity();
        $country->setIso('DE');
        $country->setId($countryId);
        $country->setPostalCodeRequired(true);
        $country->setCheckPostalCodePattern(false);
        $country->setCheckAdvancedPostalCodePattern(false);
        $country->setDefaultPostalCodePattern('\\d{5}');
        $country->setAdvancedPostalCodePattern(null);

        $result->method('get')->with($countryId)->willReturn($country);
        $this->countryRepository->expects(static::once())->method('search')->willReturn($result);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects(static::never())->method('buildViolation');

        $mock = new CustomerZipCodeValidator($this->countryRepository);

        $mock->validate('123', $this->constraint);
    }

    public function testValidZipcodeWithAdvancedValidationPattern(): void
    {
        $countryId = $this->constraint->countryId;
        static::assertNotNull($countryId);

        $result = $this->createMock(EntitySearchResult::class);
        $country = new CountryEntity();
        $country->setIso('DE');
        $country->setId($countryId);
        $country->setPostalCodeRequired(true);
        $country->setCheckPostalCodePattern(true);
        $country->setCheckAdvancedPostalCodePattern(true);
        $country->setDefaultPostalCodePattern('\\d{6}');
        $country->setAdvancedPostalCodePattern(null);

        $result->method('get')->with($countryId)->willReturn($country);
        $this->countryRepository->expects(static::once())->method('search')->willReturn($result);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects(static::never())->method('buildViolation');

        $mock = new CustomerZipCodeValidator($this->countryRepository);

        $mock->initialize($executionContext);

        $mock->validate('123456', $this->constraint);
    }

    public function testInvalidZipcodeWithAdvancedValidationPattern(): void
    {
        $countryId = $this->constraint->countryId;
        static::assertNotNull($countryId);

        $result = $this->createMock(EntitySearchResult::class);
        $country = new CountryEntity();
        $country->setIso('DE');
        $country->setId($countryId);
        $country->setPostalCodeRequired(true);
        $country->setCheckPostalCodePattern(true);
        $country->setCheckAdvancedPostalCodePattern(true);
        $country->setDefaultPostalCodePattern(null);
        $country->setAdvancedPostalCodePattern('\\d{5}');

        $result->method('get')->with($countryId)->willReturn($country);
        $this->countryRepository->expects(static::once())->method('search')->willReturn($result);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects(static::once())->method('buildViolation')->willReturnCallback(function (string $message, array $parameters = []) {
            static::assertSame($message, $this->constraint->getMessage());

            $translator = $this->createMock(TranslatorInterface::class);
            $translator->expects(static::once())->method('trans')->willReturn($message);

            return new ConstraintViolationBuilder(
                new ConstraintViolationList(),
                $this->constraint,
                $message,
                $parameters,
                '',
                '',
                '',
                $translator,
            );
        });

        $mock = new CustomerZipCodeValidator($this->countryRepository);

        $mock->initialize($executionContext);

        $mock->validate('1234567', $this->constraint);
    }

    public function testValidZipcodeWithDefaultPattern(): void
    {
        $countryId = $this->constraint->countryId;
        static::assertNotNull($countryId);

        $result = $this->createMock(EntitySearchResult::class);
        $country = new CountryEntity();
        $country->setIso('DE');
        $country->setId($countryId);
        $country->setPostalCodeRequired(true);
        $country->setCheckPostalCodePattern(true);
        $country->setCheckAdvancedPostalCodePattern(false);
        $country->setDefaultPostalCodePattern('\\d{5}');
        $country->setAdvancedPostalCodePattern(null);

        $result->method('get')->with($countryId)->willReturn($country);
        $this->countryRepository->expects(static::once())->method('search')->willReturn($result);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects(static::never())->method('buildViolation');

        $mock = new CustomerZipCodeValidator($this->countryRepository);

        $mock->initialize($executionContext);

        $mock->validate('12345', $this->constraint);
    }

    public function testInValidZipcodeWithDefaultPattern(): void
    {
        $countryId = $this->constraint->countryId;
        static::assertNotNull($countryId);

        $result = $this->createMock(EntitySearchResult::class);
        $country = new CountryEntity();
        $country->setIso('DE');
        $country->setId($countryId);
        $country->setPostalCodeRequired(true);
        $country->setCheckPostalCodePattern(true);
        $country->setCheckAdvancedPostalCodePattern(false);
        $country->setDefaultPostalCodePattern('\\d{5}');
        $country->setAdvancedPostalCodePattern(null);

        $result->method('get')->with($countryId)->willReturn($country);
        $this->countryRepository->expects(static::once())->method('search')->willReturn($result);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects(static::once())->method('buildViolation')->willReturnCallback(function (string $message, array $parameters = []) {
            static::assertSame($message, $this->constraint->getMessage());

            $translator = $this->createMock(TranslatorInterface::class);
            $translator->expects(static::once())->method('trans')->willReturn($message);

            return new ConstraintViolationBuilder(
                new ConstraintViolationList(),
                $this->constraint,
                $message,
                $parameters,
                '',
                '',
                '',
                $translator,
            );
        });

        $mock = new CustomerZipCodeValidator($this->countryRepository);

        $mock->initialize($executionContext);

        $mock->validate('123', $this->constraint);
    }
}
