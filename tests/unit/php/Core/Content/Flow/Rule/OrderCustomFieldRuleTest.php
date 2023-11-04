<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Content\Flow\Rule\OrderCustomFieldRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Customer\Rule\TestRuleScope;

/**
 * @package business-ops
 *
 * @internal
 *
 * @group rules
 *
 * @covers \Shopware\Core\Content\Flow\Rule\OrderCustomFieldRule
 */
#[Package('business-ops')]
class OrderCustomFieldRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private const CUSTOM_FIELD_NAME = 'custom_test';

    private OrderCustomFieldRule $rule;

    private OrderEntity $order;

    protected function setUp(): void
    {
        $this->rule = new OrderCustomFieldRule();

        $this->order = new OrderEntity();
    }

    public function testGetName(): void
    {
        static::assertSame('orderCustomField', $this->rule->getName());
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $scope = $this->createMock(TestRuleScope::class);

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    public function testGetConstraintsWithRenderedField(): void
    {
        $this->rule->assign([
            'renderedField' => [
                'type' => 'string',
            ],
        ]);

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('renderedFieldValue', $ruleConstraints, 'Rule Constraint renderedFieldValue is not defined');
    }

    public function testMatch(): void
    {
        $this->order->assign(['customFields' => [
            self::CUSTOM_FIELD_NAME => 'my_invalid_value',
        ]]);

        $scope = new FlowRuleScope($this->order, new Cart('test'), $this->createMock(SalesChannelContext::class));

        $this->rule->assign(
            [
                'operator' => '=',
                'renderedField' => [
                    'type' => 'string',
                    'name' => self::CUSTOM_FIELD_NAME,
                ],
                'renderedFieldValue' => 'my_test_value',
            ]
        );

        static::assertFalse($this->rule->match($scope));
    }
}
