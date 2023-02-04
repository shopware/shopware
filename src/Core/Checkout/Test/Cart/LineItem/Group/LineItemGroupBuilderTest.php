<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupPackagerNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupSorterNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroup;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupServiceRegistry;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupCountPackager;
use Shopware\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupUnitPriceGrossPackager;
use Shopware\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupUnitPriceNetPackager;
use Shopware\Core\Checkout\Cart\LineItem\Group\ProductLineItemProvider;
use Shopware\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AnyRuleLineItemMatcher;
use Shopware\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AnyRuleMatcher;
use Shopware\Core\Checkout\Cart\LineItem\Group\Sorter\LineItemGroupPriceAscSorter;
use Shopware\Core\Checkout\Cart\LineItem\Group\Sorter\LineItemGroupPriceDescSorter;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Fakes\FakeLineItemGroupSorter;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Fakes\FakeLineItemGroupTakeAllPackager;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Fakes\FakeSequenceSupervisor;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Fakes\FakeTakeAllRuleMatcher;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemGroupTestFixtureBehaviour;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\RulesTestFixtureBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionSetGroupTestFixtureBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class LineItemGroupBuilderTest extends TestCase
{
    use PromotionSetGroupTestFixtureBehaviour;
    use LineItemTestFixtureBehaviour;
    use LineItemGroupTestFixtureBehaviour;
    use RulesTestFixtureBehaviour;
    use PromotionTestFixtureBehaviour;
    use IntegrationTestBehaviour;

    private const KEY_PACKAGER_COUNT = 'COUNT';

    private const KEY_SORTER_PRICE_ASC = 'PRICE_ASC';
    private const KEY_SORTER_PRICE_DESC = 'PRICE_DESC';

    private SalesChannelContext $context;

    private FakeSequenceSupervisor $fakeSequenceSupervisor;

    private FakeLineItemGroupTakeAllPackager $fakeTakeAllPackager;

    private FakeLineItemGroupSorter $fakeSorter;

    private FakeTakeAllRuleMatcher $fakeTakeAllRuleMatcher;

    private LineItemGroupBuilder $unitTestBuilder;

    private LineItemGroupBuilder $integrationTestBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $this->context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $this->fakeSequenceSupervisor = new FakeSequenceSupervisor();
        $this->fakeTakeAllPackager = new FakeLineItemGroupTakeAllPackager('FAKE-PACKAGER', $this->fakeSequenceSupervisor);
        $this->fakeSorter = new FakeLineItemGroupSorter('FAKE-SORTER', $this->fakeSequenceSupervisor);
        $this->fakeTakeAllRuleMatcher = new FakeTakeAllRuleMatcher($this->fakeSequenceSupervisor);

        $quantityPriceCalculator = $this->createQuantityPriceCalculator();

        $this->integrationTestBuilder = new LineItemGroupBuilder(
            new LineItemGroupServiceRegistry(
                [
                    $this->fakeTakeAllPackager,
                ],
                [
                    $this->fakeSorter,
                ]
            ),
            $this->fakeTakeAllRuleMatcher,
            new LineItemQuantitySplitter($quantityPriceCalculator),
            new ProductLineItemProvider()
        );

        $this->unitTestBuilder = new LineItemGroupBuilder(
            new LineItemGroupServiceRegistry(
                [
                    new LineItemGroupCountPackager(),
                    new LineItemGroupUnitPriceGrossPackager(),
                    new LineItemGroupUnitPriceNetPackager(),
                ],
                [
                    new LineItemGroupPriceAscSorter(),
                    new LineItemGroupPriceDescSorter(),
                ]
            ),
            new AnyRuleMatcher(new AnyRuleLineItemMatcher()),
            new LineItemQuantitySplitter($quantityPriceCalculator),
            new ProductLineItemProvider()
        );
    }

    /**
     * This test verifies that our extractor starts
     * with the sorting, then proceeds with rule matching and packagers.
     * This helps us to avoid any dependencies to rules inside sorters or packagers
     *
     * @group lineitemgroup
     */
    public function testRulesMatchingFirst(): void
    {
        $cart = $this->buildCart(1);

        $group = $this->buildGroup('FAKE-PACKAGER', 2, 'FAKE-SORTER', new RuleCollection());

        $this->integrationTestBuilder->findGroupPackages([$group], $cart, $this->context);

        $countMatcher = $this->fakeTakeAllRuleMatcher->getSequenceCount();

        $countSorter = $this->fakeSorter->getSequenceCount();

        $countPackager = $this->fakeTakeAllPackager->getSequenceCount();

        // check if the matcher is called before the other objects
        $isCalledFirst = ($countSorter < $countMatcher) && ($countMatcher < $countPackager);

        static::assertTrue($isCalledFirst, 'Matcher: ' . $countMatcher . ', Sorter: ' . $countSorter . ', Packager: ' . $countPackager);
    }

    /**
     * This test verifies that our extractor uses the sorter
     * once before the rules matcher and before the packager.
     * We have to do this before iterating through the cart
     * because we modify items there which means the price might get lost.
     * Also due to performance its better if we sort once and then
     * package our groups.
     *
     * @group lineitemgroup
     */
    public function testSortingIsCalled(): void
    {
        $cart = $this->buildCart(1);

        $group = $this->buildGroup('FAKE-PACKAGER', 2, 'FAKE-SORTER', new RuleCollection());

        $this->integrationTestBuilder->findGroupPackages([$group], $cart, $this->context);

        $countMatcher = $this->fakeTakeAllRuleMatcher->getSequenceCount();

        $countSorter = $this->fakeSorter->getSequenceCount();

        $countPackager = $this->fakeTakeAllPackager->getSequenceCount();

        // check if the matcher is called before the other objects
        $isCalledMiddle = ($countSorter < $countMatcher) && ($countSorter < $countPackager);

        static::assertTrue($isCalledMiddle, 'Matcher: ' . $countMatcher . ', Sorter: ' . $countSorter . ', Packager: ' . $countPackager);
    }

    /**
     * This test verifies that our extractor uses the packager
     * after the rules matcher and after the sorter.
     *
     * @group lineitemgroup
     */
    public function testPackagerIsCalled(): void
    {
        $cart = $this->buildCart(1);

        $group = $this->buildGroup('FAKE-PACKAGER', 2, 'FAKE-SORTER', new RuleCollection());

        $this->integrationTestBuilder->findGroupPackages([$group], $cart, $this->context);

        $countMatcher = $this->fakeTakeAllRuleMatcher->getSequenceCount();

        $countSorter = $this->fakeSorter->getSequenceCount();

        $countPackager = $this->fakeTakeAllPackager->getSequenceCount();

        // check if the matcher is called before the other objects
        $isCalledLast = ($countPackager > $countMatcher) && ($countPackager > $countSorter);

        static::assertTrue($isCalledLast, 'Matcher: ' . $countMatcher . ', Sorter: ' . $countSorter . ', Packager: ' . $countPackager);
    }

    /**
     * This test verifies that we only build 1 group, if not enough items exist.
     * We have a group definition of 2 items.
     * Our cart has only 3 items, thus it's only possible to build 1 group in the end,
     * which consists of 2 different items.
     *
     * @group lineitemgroup
     */
    public function testCanOnlyFind1Group(): void
    {
        $cart = $this->buildCart(3);

        $groupDefinition = $this->buildGroup(self::KEY_PACKAGER_COUNT, 2, self::KEY_SORTER_PRICE_ASC, new RuleCollection());

        $result = $this->unitTestBuilder->findGroupPackages([$groupDefinition], $cart, $this->context);

        /** @var LineItemQuantity[] $items */
        $items = array_values($result->getGroupTotalResult($groupDefinition));

        static::assertCount(2, $items);
    }

    /**
     * This test verifies that we build as many group results as possible.
     * We make groups for every 2 items. Our cart has 7 items, so we
     * have a total of 6 (3 x 2) resulting items.
     *
     * @group lineitemgroup
     */
    public function testShouldFind3Groups(): void
    {
        $cart = $this->buildCart(7);

        $groupDefinition = $this->buildGroup(self::KEY_PACKAGER_COUNT, 2, self::KEY_SORTER_PRICE_ASC, new RuleCollection());

        $result = $this->unitTestBuilder->findGroupPackages([$groupDefinition], $cart, $this->context);

        /** @var LineItemQuantity[] $items */
        $items = array_values($result->getGroupTotalResult($groupDefinition));

        static::assertCount(6, $items);
    }

    /**
     * This test verifies a bug fix in the "rest-of-cart" algorithm with this set of products.
     * We add 3 different products (10x quantity for all products).
     * Our group builder rule should only consider the first 2 products (20 items).
     * We build groups of 5 items, which means we should get a result
     * of 4 found groups in the end.
     *
     * @group lineitemgroup
     */
    public function testShouldFindGroupsWithRule(): void
    {
        $cart = $this->buildCart(0);

        $item1 = $this->createProductItem(10, 10);
        $item2 = $this->createProductItem(20, 10);
        $item3 = $this->createProductItem(50, 10);

        $item1->setReferencedId($item1->getId());
        $item2->setReferencedId($item2->getId());
        $item3->setReferencedId($item3->getId());

        $item1->setQuantity(10);
        $item2->setQuantity(10);
        $item3->setQuantity(10);

        $cart->addLineItems(new LineItemCollection([$item1, $item2, $item3]));

        $rules = new AndRule(
            [
                $this->getProductsRule([$item1->getReferencedId(), $item2->getReferencedId()]),
            ]
        );

        $ruleEntity = new RuleEntity();
        $ruleEntity->setId(Uuid::randomHex());
        $ruleEntity->setPayload($rules);

        $group = $this->buildGroup(
            self::KEY_PACKAGER_COUNT,
            5,
            self::KEY_SORTER_PRICE_DESC,
            new RuleCollection([$ruleEntity])
        );

        $result = $this->unitTestBuilder->findGroupPackages([$group], $cart, $this->context);
        $groupCount = $result->getGroupResult($group);

        static::assertCount(4, $groupCount);
    }

    /**
     * @group lineitemgroup
     */
    public function testShouldFindGroupsWithListPriceRule(): void
    {
        $cart = $this->buildCart(0);

        $item1 = $this->createProductItem(10, 10, 20);
        $item2 = $this->createProductItem(20, 10, 30);
        $item3 = $this->createProductItem(50, 10, 100);

        $item1->setReferencedId($item1->getId());
        $item2->setReferencedId($item2->getId());
        $item3->setReferencedId($item3->getId());

        $item1->setQuantity(10);
        $item2->setQuantity(10);
        $item3->setQuantity(10);

        $cart->addLineItems(new LineItemCollection([$item1, $item2, $item3]));

        $rules = new AndRule(
            [
                $this->getLineItemListPriceRule(25),
            ]
        );

        $ruleEntity = new RuleEntity();
        $ruleEntity->setId(Uuid::randomHex());
        $ruleEntity->setPayload($rules);

        $group = $this->buildGroup(
            self::KEY_PACKAGER_COUNT,
            5,
            self::KEY_SORTER_PRICE_DESC,
            new RuleCollection([$ruleEntity])
        );

        $result = $this->unitTestBuilder->findGroupPackages([$group], $cart, $this->context);
        $groupCount = $result->getGroupResult($group);

        static::assertCount(4, $groupCount);
    }

    /**
     * This test verifies that we get a correct exception
     * if our provided packager has not been found.
     *
     * @group lineitemgroup
     */
    public function testPackagerNotFound(): void
    {
        $cart = $this->buildCart(3);

        $group = $this->buildGroup('UNKNOWN', 2, self::KEY_SORTER_PRICE_ASC, new RuleCollection());

        $this->expectException(LineItemGroupPackagerNotFoundException::class);

        $this->unitTestBuilder->findGroupPackages([$group], $cart, $this->context);
    }

    /**
     * This test verifies that we get a correct exception
     * if our sorter has not been found.
     *
     * @group lineitemgroup
     */
    public function testSorterNotFound(): void
    {
        $cart = $this->buildCart(3);

        $group = $this->buildGroup(self::KEY_PACKAGER_COUNT, 2, 'UNKNOWN', new RuleCollection());

        $this->expectException(LineItemGroupSorterNotFoundException::class);

        $this->unitTestBuilder->findGroupPackages([$group], $cart, $this->context);
    }

    /**
     * Builds a cart with the number of provided products.
     *
     * @throws CartException
     */
    private function buildCart(int $productCount): Cart
    {
        $products = [];

        for ($i = 1; $i <= $productCount; ++$i) {
            $products[] = $this->createProductItem(100, 0);
        }

        $cart = new Cart('token');
        $cart->addLineItems(new LineItemCollection($products));

        return $cart;
    }

    private function createQuantityPriceCalculator(): QuantityPriceCalculator
    {
        $priceRounding = new CashRounding();

        $taxCalculator = new TaxCalculator();

        return new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, $priceRounding),
            new NetPriceCalculator($taxCalculator, $priceRounding),
        );
    }
}
