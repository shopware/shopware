<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerEmailUnique::class)]
class CustomerEmailUniqueTest extends TestCase
{
    public function testConstructWithSalesChannelContext(): void
    {
        $salesChannelContext = $this->createSalesChannelContext();

        $constraint = new CustomerEmailUnique(salesChannelContext: $salesChannelContext);

        static::assertSame($salesChannelContext, $constraint->getSalesChannelContext());
        static::assertSame('The email address {{ email }} is already in use.', $constraint->getMessage());
    }

    public function testConstructWithCustomMessage(): void
    {
        $constraint = new CustomerEmailUnique(
            salesChannelContext: $this->createSalesChannelContext(),
            message: 'Custom message for {{ email }}.'
        );

        static::assertSame('Custom message for {{ email }}.', $constraint->getMessage());
    }

    public function testConstructWithOptionsThrows(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(
                CustomerEmailUnique::class,
                '__construct',
                'v6.8.0.0',
                'Use $salesChannelContext argument instead of providing it in $options array'
            )
        ));

        new CustomerEmailUnique(['salesChannelContext' => $this->createSalesChannelContext()]);
    }

    public function testGetContextThrows(): void
    {
        $constraint = new CustomerEmailUnique(salesChannelContext: $this->createSalesChannelContext());

        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(
                CustomerEmailUnique::class,
                'getContext',
                'v6.8.0.0',
                'getSalesChannelContext->getContext()'
            )
        ));

        $constraint->getContext();
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructWithoutSalesChannelContextThrows(): void
    {
        $this->expectExceptionObject(new MissingOptionsException(
            'Option "salesChannelContext" must be given for constraint ' . CustomerEmailUnique::class,
            ['context']
        ));

        new CustomerEmailUnique(salesChannelContext: null);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testConstructWithOptionsArray(): void
    {
        $salesChannelContext = $this->createSalesChannelContext();

        $constraint = new CustomerEmailUnique(['salesChannelContext' => $salesChannelContext]);

        static::assertSame($salesChannelContext, $constraint->getSalesChannelContext());
        static::assertSame($salesChannelContext->getContext(), $constraint->getContext());
        static::assertSame('The email address {{ email }} is already in use.', $constraint->getMessage());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructWithOptionsArrayWithoutSalesChannelContextThrows(): void
    {
        $this->expectExceptionObject(new MissingOptionsException(
            'Option "salesChannelContext" must be given for constraint ' . CustomerEmailUnique::class,
            ['context']
        ));

        new CustomerEmailUnique([]);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructWithOptionsArrayWithInvalidContextThrows(): void
    {
        $this->expectExceptionObject(new MissingOptionsException(
            'Option "context" must be given for constraint ' . CustomerEmailUnique::class,
            ['context']
        ));

        /** @phpstan-ignore argument.type (intentionally wrong option type for test purpose) */
        new CustomerEmailUnique([
            'salesChannelContext' => $this->createSalesChannelContext(),
            'context' => 'not-a-context',
        ]);
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $context = Context::createDefaultContext();

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        return $salesChannelContext;
    }
}
