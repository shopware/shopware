<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\AdminSalesChannelSourceRule;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Unit\Core\Checkout\Customer\Rule\TestRuleScope;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(AdminSalesChannelSourceRule::class)]
#[Group('rules')]
class AdminSalesChannelSourceRuleTest extends TestCase
{
    private AdminSalesChannelSourceRule $rule;

    protected function setUp(): void
    {
        $this->rule = new AdminSalesChannelSourceRule();
    }

    public function testGetName(): void
    {
        static::assertEquals('adminSalesChannelSource', $this->rule->getName());
    }

    public function testRuleConfig(): void
    {
        $config = $this->rule->getConfig();
        static::assertEquals([
            'fields' => [
                'hasAdminSalesChannelSource' => [
                    'name' => 'hasAdminSalesChannelSource',
                    'type' => 'bool',
                    'config' => [],
                ],
            ],
            'operatorSet' => null,
        ], $config->getData());
    }

    public function testGetConstraints(): void
    {
        $rule = new AdminSalesChannelSourceRule();
        $constraints = $rule->getConstraints();

        static::assertArrayHasKey('hasAdminSalesChannelSource', $constraints, 'Constraint hasAdminSalesChannelSource not found in Rule');
        static::assertEquals($constraints['hasAdminSalesChannelSource'], [
            new Type(['type' => 'bool']),
        ]);
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $scope = new TestRuleScope(Generator::createSalesChannelContext());

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    #[DataProvider('getCaseTestMatchValues')]
    public function testMatch(AdminSalesChannelSourceRule $rule, SalesChannelContext $context, bool $isMatching): void
    {
        $scope = new CheckoutRuleScope($context);

        $match = $rule->match($scope);
        static::assertEquals($match, $isMatching);
    }

    public static function getCaseTestMatchValues(): \Generator
    {
        $contextAdminSource = new AdminSalesChannelApiSource(
            'test-sales-channel-id',
            new Context(new AdminApiSource(null))
        );

        yield 'Condition is not processed by Admin SalesChannel source => Does not match because the order is processed by Admin SalesChannel source' => [
            new AdminSalesChannelSourceRule(false),
            Generator::createSalesChannelContext(new Context($contextAdminSource)),
            false,
        ];

        yield 'Condition is processed by Admin SalesChannel source => Matches because the order is processed by Admin SalesChannel source' => [
            new AdminSalesChannelSourceRule(true),
            Generator::createSalesChannelContext(new Context($contextAdminSource)),
            true,
        ];

        yield 'Condition is processed by Admin SalesChannel source => Does not match because the order is not processed by Admin SalesChannel source' => [
            new AdminSalesChannelSourceRule(true),
            Generator::createSalesChannelContext(),
            false,
        ];

        yield 'Condition is not processed by Admin SalesChannel source => Matches because the order is not processed by Admin SalesChannel source' => [
            new AdminSalesChannelSourceRule(false),
            Generator::createSalesChannelContext(),
            true,
        ];
    }
}
