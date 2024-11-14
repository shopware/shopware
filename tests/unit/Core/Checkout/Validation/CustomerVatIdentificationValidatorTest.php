<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Validation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentificationValidator;
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
    private Connection&MockObject $connection;

    private ExecutionContextInterface&MockObject $context;

    private CustomerVatIdentificationValidator $validator;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->context = $this->createMock(ExecutionContextInterface::class);

        $this->validator = new CustomerVatIdentificationValidator($this->connection);
        $this->validator->initialize($this->context);
    }

    public function testCaseSensitivityOfPattern(): void
    {
        $this->connection
            ->method('fetchAssociative')
            ->willReturn(['check_vat_id_pattern' => 1, 'vat_id_pattern' => '[A-Z]+']);

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder
            ->expects(static::once())
            ->method('setParameter')
            ->willReturnSelf();

        $builder
            ->expects(static::once())
            ->method('setCode')
            ->willReturnSelf();

        $builder
            ->expects(static::once())
            ->method('addViolation');

        $this->context
            ->expects(static::once())
            ->method('buildViolation')
            ->willReturn($builder);

        $constraint = new CustomerVatIdentification(['countryId' => Uuid::randomHex(), 'shouldCheck' => true]);

        $this->validator->validate(['abc'], $constraint);
    }
}
