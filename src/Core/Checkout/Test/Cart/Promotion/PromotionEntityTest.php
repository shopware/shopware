<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionEntityTest extends TestCase
{
    /**
     * @var PromotionEntity
     */
    private $promotion = null;

    /**
     * @var MockObject
     */
    private $checkoutContext = null;

    /**
     * @throws \ReflectionException
     */
    public function setUp(): void
    {
        $rulePersona = new RuleEntity();
        $rulePersona->setId('PERSONA-1');

        $ruleScope = new RuleEntity();
        $ruleScope->setId('SCOPE-1');

        $this->promotion = new PromotionEntity();
        $this->promotion->setPersonaRules(new RuleCollection([$rulePersona]));
        $this->promotion->setScopeRule($ruleScope);

        $this->checkoutContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
    }

    /**
     * This test verifies that our validation allows the
     * promotion based on the persona rule. For this, the ruleID
     * has to occur in the current checkout context.
     *
     * @test
     * @group promotions
     */
    public function testPersonaRuleIsRecognizedInContext()
    {
        $checkoutRuleIds = [
            'OTHER-RULE',
            'PERSONA-1',
        ];

        $this->checkoutContext->expects(static::any())->method('getRuleIds')->willReturn($checkoutRuleIds);

        $isValid = $this->promotion->isPersonaConditionValid($this->checkoutContext);

        static::assertTrue($isValid);
    }

    /**
     * This test verifies that our validation prohibits the
     * promotion based on the persona rule.
     * In this case, our rule does not occur in the checkout context
     *
     * @test
     * @group promotions
     */
    public function testPersonaRuleIsNotInContext()
    {
        $contextRuleIDs = [
            'OTHER-RULE1',
            'OTHER-RULE2',
        ];

        $this->checkoutContext->expects(static::any())->method('getRuleIds')->willReturn($contextRuleIDs);

        $isValid = $this->promotion->isPersonaConditionValid($this->checkoutContext);

        static::assertFalse($isValid);
    }

    /**
     * If no persona rule has been set, then the
     * promotion is always valid.
     * This does just mean we have no restriction.
     *
     * @test
     * @group promotions
     */
    public function testPersonaRuleValidIfEmpty()
    {
        $checkoutRuleIDs = [
            'OTHER-RULE',
        ];

        $promotionWithoutRule = new PromotionEntity();

        $this->checkoutContext->expects(static::any())->method('getRuleIds')->willReturn($checkoutRuleIDs);

        $isValid = $promotionWithoutRule->isPersonaConditionValid($this->checkoutContext);

        static::assertTrue($isValid);
    }

    /**
     * This test verifies that our validation allows the
     * promotion based on the scope rule. For this, the ruleID
     * has to occur in the current checkout context.
     *
     * @test
     * @group promotions
     */
    public function testScopeRuleIsRecognizedInContext()
    {
        $checkoutRuleIds = [
            'OTHER-RULE',
            'SCOPE-1',
        ];

        $this->checkoutContext->expects(static::any())->method('getRuleIds')->willReturn($checkoutRuleIds);

        $isValid = $this->promotion->isScopeValid($this->checkoutContext);

        static::assertTrue($isValid);
    }

    /**
     * This test verifies that our validation prohibits the
     * promotion based on the scope rule.
     * In this case, our rule does not occur in the checkout context.
     *
     * @test
     * @group promotions
     */
    public function testScopeRuleIsNotInContext()
    {
        $checkoutRuleIDs = [
            'OTHER-RULE1',
            'OTHER-RULE2',
        ];

        $this->checkoutContext->expects(static::any())->method('getRuleIds')->willReturn($checkoutRuleIDs);

        $isValid = $this->promotion->isScopeValid($this->checkoutContext);

        static::assertFalse($isValid);
    }

    /**
     * If no scope rule has been set, then the promotion is always
     * valid within the scope check.
     * This does just mean we have no restriction.
     *
     * @test
     * @group promotions
     */
    public function testScopeRuleValidIfEmpty()
    {
        $checkoutRuleIDs = [
            'OTHER-RULE',
        ];

        $promotionWithoutRule = new PromotionEntity();

        $this->checkoutContext->expects(static::any())->method('getRuleIds')->willReturn($checkoutRuleIDs);

        $isValid = $promotionWithoutRule->isScopeValid($this->checkoutContext);

        static::assertTrue($isValid);
    }
}
