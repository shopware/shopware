<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Content\Flow\Rule\OrderCreatedByAdminRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Customer\Rule\TestRuleScope;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(OrderCreatedByAdminRule::class)]
#[Group('rules')]
class OrderCreatedByAdminRuleTest extends TestCase
{
    private OrderCreatedByAdminRule $rule;

    protected function setUp(): void
    {
        $this->rule = new OrderCreatedByAdminRule();
    }

    public function testGetName(): void
    {
        static::assertEquals('orderCreatedByAdmin', $this->rule->getName());
    }

    public function testRuleConfig(): void
    {
        $config = $this->rule->getConfig();
        static::assertEquals([
            'fields' => [
                'shouldOrderBeCreatedByAdmin' => [
                    'name' => 'shouldOrderBeCreatedByAdmin',
                    'type' => 'bool',
                    'config' => [],
                ],
            ],
            'operatorSet' => null,
        ], $config->getData());
    }

    public function testGetConstraints(): void
    {
        $rule = new OrderCreatedByAdminRule();
        $constraints = $rule->getConstraints();

        static::assertArrayHasKey('shouldOrderBeCreatedByAdmin', $constraints, 'Constraint shouldOrderBeCreatedByAdmin not found in Rule');
        static::assertEquals($constraints['shouldOrderBeCreatedByAdmin'], [
            new NotNull(),
            new Type(['type' => 'bool']),
        ]);
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $scope = $this->createMock(TestRuleScope::class);

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    #[DataProvider('getCaseTestMatchValues')]
    public function testMatch(OrderCreatedByAdminRule $rule, OrderEntity $order, bool $isMatching): void
    {
        $scope = new FlowRuleScope(
            $order,
            new Cart('test'),
            $this->createMock(SalesChannelContext::class)
        );

        $match = $rule->match($scope);
        static::assertEquals($match, $isMatching);
    }

    public static function getCaseTestMatchValues(): \Generator
    {
        yield 'Condition is not created by admin => Not match because order created by admin' => [
            new OrderCreatedByAdminRule(false),
            (new OrderEntity())->assign(['createdById' => Uuid::randomHex()]),
            false,
        ];

        yield 'Condition is created by admin => Not match because order is registered' => [
            new OrderCreatedByAdminRule(true),
            new OrderEntity(),
            false,
        ];

        yield 'Condition is not created by admin => Match because order registered' => [
            new OrderCreatedByAdminRule(false),
            new OrderEntity(),
            true,
        ];

        yield 'Condition is created by admin => Match because order created by admin' => [
            new OrderCreatedByAdminRule(true),
            (new OrderEntity())->assign(['createdById' => Uuid::randomHex()]),
            true,
        ];
    }
}
