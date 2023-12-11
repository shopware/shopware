<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\LineItemGroupRule;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerNumberRule;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupEntity;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Rule;

/**
 * @internal
 */
#[CoversClass(PromotionEntity::class)]
class PromotionEntityTest extends TestCase
{
    /**
     * This test verifies, that we only get an
     * empty AND rule, if no precondition has been added.
     */
    #[Group('promotions')]
    public function testPreconditionRuleEmpty(): void
    {
        $promotion = new PromotionEntity();

        $expected = new AndRule();

        static::assertEquals($expected, $promotion->getPreconditionRule());
    }

    /**
     * This test verifies, that we have the correct persona
     * rule inside our precondition rule structure.
     * We simulate a new rule and rule entity, and add
     * that to the promotion.
     */
    #[Group('promotions')]
    public function testPreconditionRulePersonaRules(): void
    {
        $fakePersonaRule = new AndRule();

        $personaRuleEntity = new RuleEntity();
        $personaRuleEntity->setId('R1');
        $personaRuleEntity->setPayload($fakePersonaRule);

        $promotion = new PromotionEntity();
        $promotion->setCustomerRestriction(false);

        $promotion->setPersonaRules(new RuleCollection([$personaRuleEntity]));

        $expected = new AndRule(
            [
                new OrRule(
                    [$fakePersonaRule]
                ),
            ]
        );

        static::assertEquals($expected, $promotion->getPreconditionRule());
    }

    /**
     * This test verifies, that we have the correct
     * persona customer rules inside our precondition filter.
     * Thus we simulate a list of assigned customers, that will then be
     * converted into CustomerNumberRules and added to our main condition.
     * Why do we need separate customer rules? Because we don't want to match
     * a list of customer numbers, but only 1 single customer number...and thus only 1 single
     * rule should match within a list of rules, based on an OR condition.
     */
    #[Group('promotions')]
    public function testPreconditionRulePersonaCustomers(): void
    {
        $customer1 = new CustomerEntity();
        $customer1->setId('C1');
        $customer1->setCustomerNumber('C1');

        $customer2 = new CustomerEntity();
        $customer2->setId('C2');
        $customer2->setCustomerNumber('C2');

        $promotion = new PromotionEntity();
        $promotion->setCustomerRestriction(true);

        $promotion->setPersonaCustomers(new CustomerCollection([$customer1, $customer2]));

        $custRule1 = new CustomerNumberRule();
        $custRule1->assign(['numbers' => ['C1'], 'operator' => Rule::OPERATOR_EQ]);

        $custRule2 = new CustomerNumberRule();
        $custRule2->assign(['numbers' => ['C2'], 'operator' => Rule::OPERATOR_EQ]);

        $expected = new AndRule(
            [
                // this is the customer rules OR condition
                new OrRule(
                    [
                        $custRule1,
                        $custRule2,
                    ]
                ),
            ]
        );

        static::assertEquals($expected, $promotion->getPreconditionRule());
    }

    /**
     * This test verifies, that we have the correct cart
     * rule inside our precondition rule structure.
     * We simulate a new rule and rule entity, and add
     * that to the promotion.
     */
    #[Group('promotions')]
    public function testPreconditionRuleCartRules(): void
    {
        $fakeCartRule = new AndRule();

        $cartRuleEntity = new RuleEntity();
        $cartRuleEntity->setId('C1');
        $cartRuleEntity->setPayload($fakeCartRule);

        $promotion = new PromotionEntity();
        $promotion->setCartRules(new RuleCollection([$cartRuleEntity]));

        $expected = new AndRule(
            [
                new OrRule(
                    [$fakeCartRule]
                ),
            ]
        );

        static::assertEquals($expected, $promotion->getPreconditionRule());
    }

    /**
     * This test verifies, that we have the correct order
     * rule inside our precondition rule structure.
     * We simulate a new rule and rule entity, and add
     * that to the promotion.
     */
    #[Group('promotions')]
    public function testPreconditionRuleOrderRules(): void
    {
        $fakeOrderRule = new AndRule();

        $orderRuleEntity = new RuleEntity();
        $orderRuleEntity->setId('O1');
        $orderRuleEntity->setPayload($fakeOrderRule);

        $promotion = new PromotionEntity();
        $promotion->setOrderRules(new RuleCollection([$orderRuleEntity]));

        $expected = new AndRule(
            [
                new OrRule(
                    [$fakeOrderRule]
                ),
            ]
        );

        static::assertEquals($expected, $promotion->getPreconditionRule());
    }

    /**
     * This test verifies, that our whole structure is correct
     * if all rules and customers are filled.
     * In that case we want a wrapping AND condition with different
     * OR conditions for each part of the topics.
     * So all conditions need to match when speaking about preconditions, but only
     * 1 rule has to match within of the separate topics.
     * We also use a customer restriction, which means that only customer-assignment
     * rules are visible in the persona part.
     */
    #[Group('promotions')]
    public function testPreconditionRuleWithAllConditions(): void
    {
        $fakePersonaRule = new AndRule();
        $personaRuleEntity = new RuleEntity();
        $personaRuleEntity->setId('R1');
        $personaRuleEntity->setPayload($fakePersonaRule);

        $customer = new CustomerEntity();
        $customer->setId('CUST1');
        $customer->setCustomerNumber('CUST1');
        $custRule = new CustomerNumberRule();
        $custRule->assign(['numbers' => ['CUST1'], 'operator' => Rule::OPERATOR_EQ]);

        $fakeCartRule = new AndRule();
        $cartRuleEntity = new RuleEntity();
        $cartRuleEntity->setId('C1');
        $cartRuleEntity->setPayload($fakeCartRule);

        $fakeOrderRule = new AndRule();
        $orderRuleEntity = new RuleEntity();
        $orderRuleEntity->setId('O1');
        $orderRuleEntity->setPayload($fakeOrderRule);

        $promotion = new PromotionEntity();
        $promotion->setPersonaRules(new RuleCollection([$personaRuleEntity]));
        $promotion->setPersonaCustomers(new CustomerCollection([$customer]));
        $promotion->setCartRules(new RuleCollection([$cartRuleEntity]));
        $promotion->setOrderRules(new RuleCollection([$orderRuleEntity]));

        // we set the customer-assignment restriction mode
        // for the persona condition.
        $promotion->setCustomerRestriction(true);

        $expected = new AndRule(
            [
                new OrRule(
                    [$custRule]
                ),
                new OrRule(
                    [$fakeCartRule]
                ),
                new OrRule(
                    [$fakeOrderRule]
                ),
            ]
        );

        static::assertEquals($expected, $promotion->getPreconditionRule());
    }

    /**
     * This test verifies that all set groups in the promotion are added
     * with an AND condition. So all groups need to exist
     * to have a valid precondition rule.
     */
    #[Group('promotions')]
    public function testPreconditionRuleSetGroupsWithAndCondition(): void
    {
        $group1 = new PromotionSetGroupEntity();
        $group1->setId('g1');
        $group1->setPackagerKey('p1');
        $group1->setSorterKey('s1');
        $group1->setValue(1);

        $group2 = new PromotionSetGroupEntity();
        $group2->setId('g2');
        $group2->setPackagerKey('p2');
        $group2->setSorterKey('s2');
        $group2->setValue(2);

        $groups = new PromotionSetGroupCollection();
        $groups->add($group1);
        $groups->add($group2);

        $promotion = new PromotionEntity();
        $promotion->setId('p1');
        $promotion->setUseSetGroups(true);
        $promotion->setSetgroups($groups);

        $rule1 = new LineItemGroupRule();
        $rule1->assign(
            [
                'groupId' => $group1->getId(),
                'packagerKey' => $group1->getPackagerKey(),
                'value' => $group1->getValue(),
                'sorterKey' => $group1->getSorterKey(),
                'rules' => $group1->getSetGroupRules(),
            ]
        );

        $rule2 = new LineItemGroupRule();
        $rule2->assign(
            [
                'groupId' => $group2->getId(),
                'packagerKey' => $group2->getPackagerKey(),
                'value' => $group2->getValue(),
                'sorterKey' => $group2->getSorterKey(),
                'rules' => $group2->getSetGroupRules(),
            ]
        );

        $expected = new AndRule(
            [
                new AndRule(
                    [
                        $rule1,
                        $rule2,
                    ]
                ),
            ]
        );

        static::assertEquals($expected, $promotion->getPreconditionRule());
    }

    /**
     * This test verifies that we get the correct
     * FALSE result for hasDiscount, if no discount has been set.
     */
    #[Group('promotions')]
    public function testPromotionHasDiscountNo(): void
    {
        $promotion = new PromotionEntity();

        static::assertFalse($promotion->hasDiscount());
    }

    /**
     * This test verifies that we get the correct
     * FALSE result for hasDiscount, if discounts have been set.
     */
    #[Group('promotions')]
    public function testPromotionHasDiscountYes(): void
    {
        $discount = new PromotionDiscountEntity();
        $discount->setId('D1');

        $promotion = new PromotionEntity();
        $promotion->setDiscounts(new PromotionDiscountCollection([$discount]));

        static::assertTrue($promotion->hasDiscount());
    }
}
