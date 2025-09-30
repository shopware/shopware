<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddressValidationError::class)]
class AddressValidationErrorTest extends TestCase
{
    public function testAPI(): void
    {
        $violations = new ConstraintViolationList();
        $error = new AddressValidationError(true, $violations);

        static::assertSame('billing-address-invalid', $error->getId());
        static::assertTrue($error->isBillingAddress());
        static::assertSame('Please check your billing address for missing or invalid values.', $error->getMessage());
        static::assertSame('billing-address-invalid', $error->getMessageKey());
        static::assertSame(20, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertSame(['isBillingAddress' => true, 'violations' => $violations], $error->getParameters());
        static::assertSame($violations, $error->getViolations());
    }
}
