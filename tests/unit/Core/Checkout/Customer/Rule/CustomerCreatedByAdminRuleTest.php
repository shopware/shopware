<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerCreatedByAdminRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomerCreatedByAdminRule::class)]
#[Group('rules')]
class CustomerCreatedByAdminRuleTest extends TestCase
{
    public function testGetConstraints(): void
    {
        $rule = new CustomerCreatedByAdminRule();
        $constraints = $rule->getConstraints();

        static::assertArrayHasKey('shouldCustomerBeCreatedByAdmin', $constraints, 'Constraint shouldCustomerBeCreatedByAdmin not found in Rule');
        static::assertEquals($constraints['shouldCustomerBeCreatedByAdmin'], [
            new NotNull(),
            new Type(['type' => 'bool']),
        ]);
    }

    public function testName(): void
    {
        $rule = new CustomerCreatedByAdminRule();
        static::assertSame('customerCreatedByAdmin', $rule->getName());
    }

    public function testGetConfig(): void
    {
        $rule = new CustomerCreatedByAdminRule();
        $config = $rule->getConfig();
        static::assertEquals([
            'fields' => [
                'shouldCustomerBeCreatedByAdmin' => [
                    'name' => 'shouldCustomerBeCreatedByAdmin',
                    'type' => 'bool',
                    'config' => [],
                ],
            ],
            'operatorSet' => null,
        ], $config->getData());
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $rule = new CustomerCreatedByAdminRule();
        $scope = $this->createMock(TestRuleScope::class);

        $match = $rule->match($scope);

        static::assertFalse($match);
    }

    public function testMatchWithMissingCustomer(): void
    {
        $rule = new CustomerCreatedByAdminRule();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = new CustomerEntity();
        $customer->assign(['createdById' => Uuid::randomHex()]);

        $salesChannelContext->expects(static::once())
            ->method('getCustomer')
            ->willReturn(null);

        $scope = new CheckoutRuleScope($salesChannelContext);
        $match = $rule->match($scope);
        static::assertFalse($match);
    }

    #[DataProvider('getCaseTestMatchValues')]
    public function testMatch(CustomerCreatedByAdminRule $rule, CustomerEntity $customer, bool $isMatching): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $salesChannelContext->expects(static::once())
            ->method('getCustomer')
            ->willReturn($customer);

        $scope = new CheckoutRuleScope($salesChannelContext);
        $match = $rule->match($scope);
        static::assertEquals($match, $isMatching);
    }

    public static function getCaseTestMatchValues(): \Generator
    {
        yield 'Condition is not created by admin => Not match because customer created by admin' => [
            new CustomerCreatedByAdminRule(false),
            (new CustomerEntity())->assign(['createdById' => Uuid::randomHex()]),
            false,
        ];

        yield 'Condition is created by admin => Not match because customer is registered' => [
            new CustomerCreatedByAdminRule(true),
            new CustomerEntity(),
            false,
        ];

        yield 'Condition is not created by admin => Match because customer registered' => [
            new CustomerCreatedByAdminRule(false),
            new CustomerEntity(),
            true,
        ];

        yield 'Condition is created by admin => Match because user created by admin' => [
            new CustomerCreatedByAdminRule(true),
            (new CustomerEntity())->assign(['createdById' => Uuid::randomHex()]),
            true,
        ];
    }
}
