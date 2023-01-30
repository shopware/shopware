<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Content\Flow\Rule\OrderTagRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\Tag\TagEntity;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @package business-ops
 *
 * @internal
 *
 * @group rules
 *
 * @covers \Shopware\Core\Content\Flow\Rule\OrderTagRule
 */
class OrderTagRuleTest extends TestCase
{
    private OrderTagRule $rule;

    protected function setUp(): void
    {
        $this->rule = new OrderTagRule();
    }

    public function testGetName(): void
    {
        static::assertEquals('orderTag', $this->rule->getName());
    }

    public function testRuleConfig(): void
    {
        $expectedConfiguration = [
            'operatorSet' => [
                'operators' => [
                    Rule::OPERATOR_EQ,
                    Rule::OPERATOR_NEQ,
                    Rule::OPERATOR_EMPTY,
                ],
                'isMatchAny' => 1,
            ],
            'fields' => [
                [
                    'name' => 'identifiers',
                    'type' => 'multi-entity-id-select',
                    'config' => [
                        'entity' => 'tag',
                    ],
                ],
            ],
        ];

        $data = $this->rule->getConfig()->getData();
        static::assertEquals($expectedConfiguration, $data);
    }

    public function testConstraints(): void
    {
        $operators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ];

        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('identifiers', $constraints, 'identifiers constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraints not found');

        static::assertEquals([new NotBlank(), new ArrayOfUuid()], $constraints['identifiers']);
        static::assertEquals([new NotBlank(), new Choice($operators)], $constraints['operator']);
    }

    /**
     * @dataProvider getMatchValues
     *
     * @param array<string>|string|null $givenIdentifier
     * @param array<string> $ruleIdentifiers
     */
    public function testRuleMatching(string $operator, bool $isMatching, array $ruleIdentifiers, $givenIdentifier): void
    {
        $order = new OrderEntity();
        $tagCollection = new TagCollection();
        $orderTagIds = array_filter(\is_array($givenIdentifier) ? $givenIdentifier : [$givenIdentifier]);
        foreach ($orderTagIds as $orderTagId) {
            $tag = new TagEntity();
            $tag->setId($orderTagId);
            $tagCollection->add($tag);
        }
        $order->setTags($tagCollection);

        $scope = $this->createScope($order);
        $this->rule->assign(['identifiers' => $ruleIdentifiers, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    public function getMatchValues(): \Generator
    {
        yield 'operator_eq / not match / identifier' => [Rule::OPERATOR_EQ, false, ['kyln123', 'kyln456'], 'kyln000'];
        yield 'operator_eq / match partly / identifier' => [Rule::OPERATOR_EQ, true, ['kyln123', 'kyln456'], 'kyln123'];
        yield 'operator_eq / match full / identifier' => [Rule::OPERATOR_EQ, true, ['kyln123', 'kyln456'], ['kyln123', 'kyln456']];
        yield 'operator_neq / match / identifier' => [Rule::OPERATOR_NEQ, true, ['kyln123', 'kyln456'], 'kyln000'];
        yield 'operator_neq / not match / identifier' => [Rule::OPERATOR_NEQ, false, ['kyln123', 'kyln456'], 'kyln123'];
        yield 'operator_empty / not match / identifier' => [Rule::OPERATOR_NEQ, false, ['kyln123', 'kyln456'], 'kyln123'];
        yield 'operator_empty / match / identifier' => [Rule::OPERATOR_EMPTY, true, ['kyln123', 'kyln456'], null];
    }

    public function testNotMatchingWithUnsupportedScope(): void
    {
        $scope = $this->createMock(CartRuleScope::class);

        static::assertFalse($this->rule->match($scope));
    }

    private function createScope(OrderEntity $order): CheckoutRuleScope
    {
        $context = $this->createMock(SalesChannelContext::class);
        $cart = $this->createMock(Cart::class);

        return new FlowRuleScope($order, $cart, $context);
    }
}
