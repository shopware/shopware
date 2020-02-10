<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemTaxationRule;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class LineItemTaxationRuleTest extends TestCase
{
    /**
     * @var LineItemTaxationRule
     */
    private $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemTaxationRule();
    }

    public function testGetName(): void
    {
        static::assertEquals('cartLineItemTaxation', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
        static::assertArrayHasKey('taxIds', $ruleConstraints, 'Rule Constraint taxIds is not defined');
    }

    /**
     * @dataProvider getLineItemScopeTestData
     */
    public function testIfMatchesCorrectWithLineItemScope(array $taxIds, string $operator, string $lineItemTaxId, bool $expected): void
    {
        $this->rule->assign([
            'taxIds' => $taxIds,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItem($lineItemTaxId),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    public function getLineItemScopeTestData(): array
    {
        return [
            'single product / equal / match tax id' => [['1', '2'], Rule::OPERATOR_EQ, '1', true],
            'single product / equal / no match' => [['1', '2'], Rule::OPERATOR_EQ, '3', false],
            'single product / not equal / match tax id' => [['1', '2'], Rule::OPERATOR_NEQ, '3', true],
        ];
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScope(array $taxIds, string $operator, string $lineItemTaxId, bool $expected): void
    {
        $this->rule->assign([
            'taxIds' => $taxIds,
            'operator' => $operator,
        ]);

        $cart = new Cart('test', Uuid::randomHex());

        $cart->setLineItems(
            (new LineItemCollection([
                $this->createLineItem('1'),
                $this->createLineItem($lineItemTaxId),
            ]))
        );

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItem($lineItemTaxId),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    public function getCartRuleScopeTestData(): array
    {
        return [
            'multiple products / equal / match tax id' => [['1', '2'], Rule::OPERATOR_EQ, '2', true],
            'multiple products / equal / no match' => [['4', '5'], Rule::OPERATOR_EQ, '2', false],
            'multiple products / not equal / match tax id' => [['5', '6'], Rule::OPERATOR_NEQ, '2', true],
            'multiple products / not equal / no match tax id' => [['1', '2'], Rule::OPERATOR_NEQ, '2', false],
        ];
    }

    public function testNotAvailableOperatorIsUsed(): void
    {
        $this->rule->assign([
            'taxIds' => ['1', '2'],
            'operator' => Rule::OPERATOR_LT,
        ]);

        $this->expectException(UnsupportedOperatorException::class);

        $this->rule->match(new LineItemScope(
            $this->createLineItem('3'),
            $this->createMock(SalesChannelContext::class)
        ));
    }

    private function createLineItem(string $taxId): LineItem
    {
        return (new LineItem(Uuid::randomHex(), 'product', null, 3))
            ->setPayloadValue('taxId', $taxId);
    }
}
