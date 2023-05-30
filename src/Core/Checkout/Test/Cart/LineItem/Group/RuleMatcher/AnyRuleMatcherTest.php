<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group\RuleMatcher;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AnyRuleLineItemMatcher;
use Shopware\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AnyRuleMatcher;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemGroupTestFixtureBehaviour;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\RulesTestFixtureBehaviour;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
class AnyRuleMatcherTest extends TestCase
{
    use RulesTestFixtureBehaviour;
    use LineItemTestFixtureBehaviour;
    use LineItemGroupTestFixtureBehaviour;

    private const KEY_PACKAGER_COUNT = 'PACKAGER_COUNT';
    private const KEY_SORTER_PRICE_ASC = 'PRICE_ASC';

    private MockObject&SalesChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
    }

    /**
     * This test verifies that our line item matching works correctly with 1 rule.
     * We create a group with a rule for minimum item price of 50.
     * This means, that only line items with 50 or higher, are matched
     * with a positive result.
     *
     * @group lineitemgroup
     */
    public function testMatchesForSingleRule(): void
    {
        $rules = new AndRule(
            [
                $this->getMinPriceRule(50),
            ]
        );

        $ruleEntity = new RuleEntity();
        $ruleEntity->setId('R1');
        $ruleEntity->setPayload($rules);

        // create our 2 test product line items
        $product50 = new LineItem('ABC1', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $product50->setPrice(new CalculatedPrice(50, 50, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $productLower50 = new LineItem('ABC2', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $productLower50->setPrice(new CalculatedPrice(49, 49, new CalculatedTaxCollection(), new TaxRuleCollection()));

        // create our group with our price rule
        // and use it to match both our products
        $group = $this->buildGroup(
            self::KEY_PACKAGER_COUNT,
            1,
            self::KEY_SORTER_PRICE_ASC,
            new RuleCollection([$ruleEntity])
        );

        $matcher = new AnyRuleMatcher(new AnyRuleLineItemMatcher());

        $matchedItems = $matcher->getMatchingItems($group, new LineItemFlatCollection([$productLower50, $product50]), $this->context);

        static::assertCount(1, $matchedItems);
        static::assertSame($product50, $matchedItems->getElements()[0]);
    }

    /**
     * This test verifies that our line item matching works correctly with 2 rule combinations.
     * We create a group with a rule for minimum item price of 50 and minimum quantity of 3.
     * We have 4 combinations within our products. The multi rules work with a OR condition, so we
     * should get 3 out of our 4 products that match. Only the product with neither quantity nor price condition
     * should not match our group rules.
     *
     * @group lineitemgroup
     */
    public function testMatchesForMultipleRules(): void
    {
        $minPrice = 50;
        $minQuantity = 3;

        $productHighQuantity1Id = Uuid::randomBytes();
        $productHighQuantity2Id = Uuid::randomBytes();
        $productLowQuantity1Id = Uuid::randomBytes();
        $productLowQuantity2Id = Uuid::randomBytes();

        // create our test product line items
        $productHighQuantityHighPrice = $this->createProductItem($minPrice, 0);
        $productHighQuantityHighPrice->setId($productHighQuantity1Id);
        $productHighQuantityHighPrice->setReferencedId($productHighQuantity1Id);
        $productHighQuantityHighPrice->setQuantity($minQuantity);

        $productHighQuantityLowPrice = $this->createProductItem($minPrice - 0.1, 0);
        $productHighQuantityLowPrice->setId($productHighQuantity2Id);
        $productHighQuantityLowPrice->setReferencedId($productHighQuantity2Id);
        $productHighQuantityLowPrice->setQuantity($minQuantity);

        $productLowQuantityHighPrice = $this->createProductItem($minPrice, 0);
        $productLowQuantityHighPrice->setId($productLowQuantity1Id);
        $productLowQuantityHighPrice->setReferencedId($productLowQuantity1Id);
        $productLowQuantityHighPrice->setQuantity($minQuantity - 1);

        $productLowQuantityLowPrice = $this->createProductItem($minPrice - 0.1, 0);
        $productLowQuantityLowPrice->setId($productLowQuantity2Id);
        $productLowQuantityLowPrice->setReferencedId($productLowQuantity2Id);
        $productLowQuantityLowPrice->setQuantity($minQuantity - 1);

        $rulesMinPrice = new RuleEntity();
        $rulesMinPrice->setId(Uuid::randomBytes());
        $rulesMinPrice->setPayload(new OrRule(
            [
                $this->getMinQuantityRule($productHighQuantity1Id, $minQuantity),
                $this->getMinQuantityRule($productHighQuantity2Id, $minQuantity),
                $this->getMinQuantityRule($productLowQuantity1Id, $minQuantity),
                $this->getMinQuantityRule($productLowQuantity2Id, $minQuantity),
            ]
        ));

        $rulesMinQuantity = new RuleEntity();
        $rulesMinQuantity->setId(Uuid::randomBytes());
        $rulesMinQuantity->setPayload(new AndRule([$this->getMinPriceRule($minPrice)]));

        // create our group with our price and quantity rule
        // and use it to match both our products
        $group = $this->buildGroup(
            self::KEY_PACKAGER_COUNT,
            1,
            self::KEY_SORTER_PRICE_ASC,
            new RuleCollection([$rulesMinPrice, $rulesMinQuantity])
        );

        $matcher = new AnyRuleMatcher(new AnyRuleLineItemMatcher());

        $matchedItems = $matcher->getMatchingItems(
            $group,
            new LineItemFlatCollection(
                [
                    $productHighQuantityHighPrice,
                    $productHighQuantityLowPrice,
                    $productLowQuantityHighPrice,
                    $productLowQuantityLowPrice,
                ]
            ),
            $this->context
        );

        static::assertCount(3, $matchedItems);
        static::assertSame($productHighQuantityHighPrice, $matchedItems->getElements()[0]);
        static::assertSame($productHighQuantityLowPrice, $matchedItems->getElements()[1]);
        static::assertSame($productLowQuantityHighPrice, $matchedItems->getElements()[2]);
    }
}
