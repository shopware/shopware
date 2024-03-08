<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\IsActiveRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(IsActiveRule::class)]
#[Group('rules')]
class IsActiveRuleTest extends TestCase
{
    private IsActiveRule $rule;

    protected function setUp(): void
    {
        $this->rule = new IsActiveRule();
    }

    #[DataProvider('getCustomerScopeTestData')]
    public function testValidateRule(
        bool $isActive,
        bool $customerActiveValue,
        bool $expectedValue,
        bool $noCustomer
    ): void {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        if (!$noCustomer) {
            $customer = new CustomerEntity();
            $customer->setActive($customerActiveValue);

            $salesChannelContext
                ->method('getCustomer')
                ->willReturn($customer);
        }

        $isActiveCustomerRule = new IsActiveRule($isActive);

        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertSame($expectedValue, $isActiveCustomerRule->match($scope));
    }

    public function testConstrains(): void
    {
        $actualConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('isActive', $actualConstraints, 'Constrains not found in rule, given "isActive"');

        $isActiveConstraint = $actualConstraints['isActive'];

        static::assertEquals(new NotNull(), $isActiveConstraint[0]);
        static::assertEquals(new Type('bool'), $isActiveConstraint[1]);
    }

    public function testReturnsFalseWhenProvidingIncorrectScope(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $lineItem = new LineItem('random-id', 'line-item');

        $isActiveCustomerRule = new IsActiveRule(true);

        $scope = new LineItemScope($lineItem, $salesChannelContext);

        static::assertFalse($isActiveCustomerRule->match($scope));
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getCustomerScopeTestData(): \Traversable
    {
        yield 'match / operator yes / active customer' => [true, true, true, false];
        yield 'match / operator no / deactivated customer' => [false, false, true, false];
        yield 'no match / operator yes / deactivated customer' => [true, false, false, false];
        yield 'no match / operator no / active customer' => [false, true, false, false];
        yield 'no match / operator yes / no customer' => [true, false, false, true];
        yield 'no match / operator no / no customer' => [false, false, false, true];
    }
}
