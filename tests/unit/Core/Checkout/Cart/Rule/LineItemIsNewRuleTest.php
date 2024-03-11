<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemIsNewRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(LineItemIsNewRule::class)]
#[Group('rules')]
class LineItemIsNewRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private LineItemIsNewRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemIsNewRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemIsNew', $this->rule->getName());
    }

    /**
     * This test verifies that we have the correct constraint
     * and that no NotBlank is existing - only 1 BOOL constraint.
     * Otherwise a FALSE value would not work when saving in the administration.
     */
    #[Group('rules')]
    public function testIsNewConstraint(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        $boolType = new Type(['type' => 'bool']);

        static::assertArrayHasKey('isNew', $ruleConstraints, 'Rule Constraint isNew is not defined');
        static::assertCount(1, $ruleConstraints['isNew']);
        static::assertEquals($boolType, $ruleConstraints['isNew'][0]);
    }

    #[DataProvider('getLineItemScopeTestData')]
    public function testIfMatchesCorrectWithLineItem(bool $ruleActive, bool $isNew, bool $expected): void
    {
        $this->rule->assign(['isNew' => $ruleActive]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithIsNewMarker($isNew),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, array<bool>>
     */
    public static function getLineItemScopeTestData(): array
    {
        return [
            'rule yes / newcomer yes' => [true, true, true],
            'rule yes / newcomer no' => [true, false, false],
            'rule no / newcomer yes' => [false, true, false],
            'rule no / newcomer no' => [false, false, true],
        ];
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScope(bool $ruleActive, bool $isNew, bool $expected): void
    {
        $this->rule->assign(['isNew' => $ruleActive]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithIsNewMarker($isNew),
            $this->createLineItemWithIsNewMarker(false),
        ]);

        $cart = $this->createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScopeNested(bool $ruleActive, bool $isNew, bool $expected): void
    {
        $this->rule->assign(['isNew' => $ruleActive]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithIsNewMarker($isNew),
            $this->createLineItemWithIsNewMarker(false),
        ]);
        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, array<bool>>
     */
    public static function getCartRuleScopeTestData(): array
    {
        return [
            'rule yes / newcomer yes' => [true, true, true],
            'rule yes / newcomer no' => [true, false, false],
            'rule no / newcomer yes' => [false, true, true],
            'rule no / newcomer no' => [false, false, true],
        ];
    }

    private function createLineItemWithIsNewMarker(bool $isNew): LineItem
    {
        return $this->createLineItem()->setPayloadValue('isNew', $isNew);
    }
}
