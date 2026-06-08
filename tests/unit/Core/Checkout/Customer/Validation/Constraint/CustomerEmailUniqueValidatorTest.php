<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUniqueValidator;
use Shopware\Core\Checkout\Customer\Validation\CustomerEmailUniqueCheck;
use Shopware\Core\Checkout\Customer\Validation\CustomerEmailUniqueChecker;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @internal
 *
 * @extends ConstraintValidatorTestCase<CustomerEmailUniqueValidator>
 */
#[Package('checkout')]
#[CoversClass(CustomerEmailUniqueValidator::class)]
class CustomerEmailUniqueValidatorTest extends ConstraintValidatorTestCase
{
    private CustomerEmailUniqueChecker&MockObject $customerEmailUniqueChecker;

    protected function setUp(): void
    {
        $this->customerEmailUniqueChecker = $this->createMock(CustomerEmailUniqueChecker::class);

        parent::setUp();
    }

    public function testItIgnoresNullValue(): void
    {
        $this->customerEmailUniqueChecker->expects($this->never())
            ->method('isUnique');

        $this->validator->validate(null, $this->createConstraint());

        $this->assertNoViolation();
    }

    public function testItIgnoresEmptyString(): void
    {
        $this->customerEmailUniqueChecker->expects($this->never())
            ->method('isUnique');

        $this->validator->validate('', $this->createConstraint());

        $this->assertNoViolation();
    }

    public function testItPassesEmailAndSalesChannelScopeToChecker(): void
    {
        $email = 'customer@example.com';
        $salesChannelId = Uuid::randomHex();

        $this->customerEmailUniqueChecker->expects($this->once())
            ->method('isUnique')
            ->with(static::callback(static function (CustomerEmailUniqueCheck $check) use ($email, $salesChannelId): bool {
                static::assertSame($email, $check->email);
                static::assertNull($check->customerId);
                static::assertSame($salesChannelId, $check->boundSalesChannelId);
                static::assertFalse($check->guest);

                return true;
            }))
            ->willReturn(true);

        $this->validator->validate($email, $this->createConstraint($salesChannelId));

        $this->assertNoViolation();
    }

    public function testItBuildsViolationWhenEmailIsNotUnique(): void
    {
        $email = 'customer@example.com';
        $this->setValue($email);

        $this->customerEmailUniqueChecker->expects($this->once())
            ->method('isUnique')
            ->willReturn(false);

        $constraint = $this->createConstraint();

        $this->validator->validate($email, $constraint);

        $this->buildViolation($constraint->getMessage())
            ->setParameter('{{ email }}', '"' . $email . '"')
            ->setInvalidValue($email)
            ->setCode(CustomerEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE)
            ->assertRaised();
    }

    public function testItRejectsUnexpectedConstraintType(): void
    {
        try {
            $this->validator->validate('customer@example.com', new NotBlank());

            static::fail('Expected unexpected constraint type exception.');
        } catch (\Throwable $exception) {
            static::assertTrue(
                $exception instanceof CustomerException || $exception instanceof UnexpectedTypeException,
                \sprintf('Expected customer unexpected type exception, got %s.', $exception::class)
            );
        }
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new CustomerEmailUniqueValidator($this->customerEmailUniqueChecker);
    }

    private function createConstraint(?string $salesChannelId = null): CustomerEmailUnique
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')
            ->willReturn($salesChannelId ?? Uuid::randomHex());

        return new CustomerEmailUnique(salesChannelContext: $salesChannelContext);
    }
}
