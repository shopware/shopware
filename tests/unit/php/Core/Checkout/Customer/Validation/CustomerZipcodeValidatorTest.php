<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCodeValidator;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 * @covers \Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCodeValidator
 */
class CustomerZipcodeValidatorTest extends TestCase
{
    private CustomerZipCode $constraint;

    /**
     * @var Connection&MockObject
     */
    private $connection;

    public function setUp(): void
    {
        $this->constraint = new CustomerZipCode([
            'countryId' => Uuid::randomHex(),
        ]);

        $this->connection = $this->createMock(Connection::class);
    }

    public function testUnexpectedTypeException(): void
    {
        $mock = new CustomerZipCodeValidator($this->connection);

        try {
            $mock->validate(['zipcode' => '1235468'], $this->createMock(Constraint::class));
        } catch (\Throwable $exception) {
            static::assertInstanceOf(UnexpectedTypeException::class, $exception);
        }
    }

    public function testInValidZipcodeIsRequired(): void
    {
        $this->connection->expects(static::once())->method('fetchAssociative')->willReturn([
            'iso' => 'DE',
            'postal_code_required' => true,
            'check_postal_code_pattern' => false,
            'check_advanced_postal_code_pattern' => false,
            'advanced_postal_code_pattern' => null,
            'default_postal_code_pattern' => '\\d{5}',
        ]);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects(static::once())->method('buildViolation')->willReturnCallback(function (string $message, array $parameters = []) {
            static::assertSame($message, $this->constraint->messageRequired);

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

        $mock = new CustomerZipCodeValidator($this->connection);

        $mock->initialize($executionContext);

        $mock->validate('', $this->constraint);
    }

    public function testValidZipcodeIsRequired(): void
    {
        $this->connection->expects(static::once())->method('fetchAssociative')->willReturn([
            'iso' => 'DE',
            'postal_code_required' => true,
            'check_postal_code_pattern' => false,
            'check_advanced_postal_code_pattern' => false,
            'advanced_postal_code_pattern' => null,
            'default_postal_code_pattern' => '\\d{5}',
        ]);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects(static::never())->method('buildViolation');

        $mock = new CustomerZipCodeValidator($this->connection);

        $mock->validate('123', $this->constraint);
    }

    public function testValidZipcodeWithAdvancedValidationPattern(): void
    {
        $this->connection->expects(static::once())->method('fetchAssociative')->willReturn([
            'iso' => 'DE',
            'postal_code_required' => true,
            'check_postal_code_pattern' => true,
            'check_advanced_postal_code_pattern' => true,
            'advanced_postal_code_pattern' => '\\d{6}',
            'default_postal_code_pattern' => null,
        ]);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects(static::never())->method('buildViolation');

        $mock = new CustomerZipCodeValidator($this->connection);

        $mock->initialize($executionContext);

        $mock->validate('123456', $this->constraint);
    }

    public function testInvalidZipcodeWithAdvancedValidationPattern(): void
    {
        $this->connection->expects(static::once())->method('fetchAssociative')->willReturn([
            'iso' => 'DE',
            'postal_code_required' => true,
            'check_postal_code_pattern' => true,
            'check_advanced_postal_code_pattern' => true,
            'advanced_postal_code_pattern' => '\\d{5}',
            'default_postal_code_pattern' => null,
        ]);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects(static::once())->method('buildViolation')->willReturnCallback(function (string $message, array $parameters = []) {
            static::assertSame($message, $this->constraint->message);

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

        $mock = new CustomerZipCodeValidator($this->connection);

        $mock->initialize($executionContext);

        $mock->validate('1234567', $this->constraint);
    }

    public function testValidZipcodeWithDefaultPattern(): void
    {
        $this->connection->expects(static::once())->method('fetchAssociative')->willReturn([
            'iso' => 'DE',
            'postal_code_required' => true,
            'check_postal_code_pattern' => true,
            'check_advanced_postal_code_pattern' => false,
            'advanced_postal_code_pattern' => null,
            'default_postal_code_pattern' => '\\d{5}',
        ]);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects(static::never())->method('buildViolation');

        $mock = new CustomerZipCodeValidator($this->connection);

        $mock->initialize($executionContext);

        $mock->validate('12345', $this->constraint);
    }

    public function testInValidZipcodeWithDefaultPattern(): void
    {
        $this->connection->expects(static::once())->method('fetchAssociative')->willReturn([
            'iso' => 'DE',
            'postal_code_required' => true,
            'check_postal_code_pattern' => true,
            'check_advanced_postal_code_pattern' => false,
            'advanced_postal_code_pattern' => null,
            'default_postal_code_pattern' => '\\d{5}',
        ]);

        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects(static::once())->method('buildViolation')->willReturnCallback(function (string $message, array $parameters = []) {
            static::assertSame($message, $this->constraint->message);

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

        $mock = new CustomerZipCodeValidator($this->connection);

        $mock->initialize($executionContext);

        $mock->validate('123', $this->constraint);
    }
}
