<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class LineItemCustomFieldRuleTest extends TestCase
{
    /**
     * @var LineItemCustomFieldRule
     */
    private $rule;

    /** @var SalesChannelContext */
    private $salesChannelContext;

    protected function setUp(): void
    {
        $this->rule = new LineItemCustomFieldRule();

        $this->salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $this->salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
    }

    public function testGetName(): void
    {
        static::assertEquals('cartLineItemCustomField', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
        static::assertArrayHasKey('renderedField', $ruleConstraints, 'Rule Constraint renderedField is not defined');
        static::assertArrayHasKey('renderedFieldValue', $ruleConstraints, 'Rule Constraint renderedFieldValue is not defined');
        static::assertArrayHasKey('selectedField', $ruleConstraints, 'Rule Constraint selectedField is not defined');
        static::assertArrayHasKey('selectedFieldSet', $ruleConstraints, 'Rule Constraint selectedFieldSet is not defined');
    }

    public function testBooleanCustomFieldFalseWithNoValue(): void
    {
        $this->setupRule('custom_test', false);
        $scope = new LineItemScope($this->getLineItem(), $this->salesChannelContext);
        static::assertTrue($this->rule->match($scope));
    }

    public function testBooleanCustomFieldFalse(): void
    {
        $this->setupRule('custom_test', false);
        $scope = new LineItemScope($this->getLineItem(['custom_test' => false]), $this->salesChannelContext);
        static::assertTrue($this->rule->match($scope));
    }

    public function testBooleanCustomFieldNull(): void
    {
        $this->setupRule('custom_test', null);
        $scope = new LineItemScope($this->getLineItem(['custom_test' => false]), $this->salesChannelContext);
        static::assertTrue($this->rule->match($scope));
    }

    public function testBooleanCustomFieldInvalid(): void
    {
        $this->setupRule('custom_test', false);
        $scope = new LineItemScope($this->getLineItem(['custom_test' => true]), $this->salesChannelContext);
        static::assertFalse($this->rule->match($scope));
    }

    public function testStringCustomField(): void
    {
        $this->setupRule('custom_test', 'my_test_value');
        $scope = new LineItemScope($this->getLineItem(['custom_test' => 'my_test_value']), $this->salesChannelContext);
        static::assertTrue($this->rule->match($scope));
    }

    public function testStringCustomFieldInvalid(): void
    {
        $this->setupRule('custom_test', 'my_test_value');
        $scope = new LineItemScope($this->getLineItem(['custom_test' => 'my_invalid_value']), $this->salesChannelContext);
        static::assertFalse($this->rule->match($scope));
    }

    private function getLineItem(array $customFields = []): LineItem
    {
        $lineItem = new LineItem('', LineItem::PRODUCT_LINE_ITEM_TYPE);

        $lineItem
            ->setPayloadValue('customFields', $customFields);

        return $lineItem;
    }

    private function setupRule(string $customFieldName, $customFieldValue): void
    {
        $this->rule->assign(
            [
                'operator' => $this->rule::OPERATOR_EQ,
                'renderedField' => [
                    'type' => 'bool',
                    'name' => $customFieldName,
                ],
                'renderedFieldValue' => $customFieldValue,
            ]
        );
    }
}
