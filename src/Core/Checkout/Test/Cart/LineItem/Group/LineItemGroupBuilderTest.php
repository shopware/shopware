<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupPackagerNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupSorterNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroup;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupServiceRegistry;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupCountPackager;
use Shopware\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupUnitPriceGrossPackager;
use Shopware\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupUnitPriceNetPackager;
use Shopware\Core\Checkout\Cart\LineItem\Group\RulesMatcher\AnyRuleMatcher;
use Shopware\Core\Checkout\Cart\LineItem\Group\Sorter\LineItemGroupPriceAscSorter;
use Shopware\Core\Checkout\Cart\LineItem\Group\Sorter\LineItemGroupPriceDescSorter;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\ReferencePriceCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Fakes\FakeLineItemGroupSorter;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Fakes\FakeLineItemGroupTakeAllPackager;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Fakes\FakeSequenceSupervisor;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Fakes\FakeTakeAllRuleMatcher;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemGroupTestFixtureBehaviour;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\RulesTestFixtureBehaviour;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemGroupBuilderTest extends TestCase
{
    use LineItemTestFixtureBehaviour;
    use LineItemGroupTestFixtureBehaviour;
    use RulesTestFixtureBehaviour;

    private const KEY_PACKAGER_COUNT = 'COUNT';

    private const KEY_SORTER_PRICE_ASC = 'PRICE_ASC';
    private const KEY_SORTER_PRICE_DESC = 'PRICE_DESC';

    /**
     * @var SalesChannelContext
     */
    private $context;

    /**
     * @var FakeSequenceSupervisor
     */
    private $fakeSequenceSupervisor;

    /**
     * @var LineItemGroupPackagerInterface
     */
    private $fakeTakeAllPackager;

    /**
     * @var LineItemGroupSorterInterface
     */
    private $fakeSorter;

    /**
     * @var FakeTakeAllRuleMatcher
     */
    private $fakeTakeAllRuleMatcher;

    /**
     * @var LineItemGroupBuilder
     */
    private $unitTestBuilder;

    /**
     * @var LineItemGroupBuilder
     */
    private $integrationTestBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();

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
            new LineItemQuantitySplitter($quantityPriceCalculator)
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
            new AnyRuleMatcher(),
            new LineItemQuantitySplitter($quantityPriceCalculator)
        );
    }

    /**
     * This test verifies that our extractor starts
     * with the rule matching, before calling any sorters or packagers.
     * This helps us to avoid any dependencies to rules inside sorters or packagers
     *
     * @test
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
        $isCalledFirst = ($countMatcher < $countSorter) && ($countMatcher < $countPackager);

        static::assertTrue($isCalledFirst, 'Matcher: ' . $countMatcher . ', Sorter: ' . $countSorter . ', Packager: ' . $countPackager);
    }

    /**
     * This test verifies that our extractor uses the sorter
     * after the rules matcher and before the packager.
     *
     * @test
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
        $isCalledMiddle = ($countSorter > $countMatcher) && ($countSorter < $countPackager);

        static::assertTrue($isCalledMiddle, 'Matcher: ' . $countMatcher . ', Sorter: ' . $countSorter . ', Packager: ' . $countPackager);
    }

    /**
     * This test verifies that our extractor uses the packager
     * after the rules matcher and after the sorter.
     *
     * @test
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
     * @test
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
     * @test
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
     * This test verifies that we get a correct exception
     * if our provided packager has not been found.
     *
     * @test
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
     * @test
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
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    private function buildCart(int $productCount): Cart
    {
        $products = [];

        for ($i = 1; $i <= $productCount; ++$i) {
            $products[] = $this->createProductItem(100, 0);
        }

        $cart = new Cart('test', 'token');
        $cart->addLineItems(new LineItemCollection($products));

        return $cart;
    }

    private function createQuantityPriceCalculator()
    {
        $detector = $this->createMock(TaxDetector::class);
        $detector->method('useGross')->willReturn(false);
        $detector->method('isNetDelivery')->willReturn(false);

        $priceRounding = new PriceRounding();
        $referencePriceCalculator = new ReferencePriceCalculator($priceRounding);

        $taxCalculator = new TaxCalculator(
            new TaxRuleCalculator()
        );

        $quantityPriceCalculator = new QuantityPriceCalculator(
            new GrossPriceCalculator($taxCalculator, $priceRounding, $referencePriceCalculator),
            new NetPriceCalculator($taxCalculator, $priceRounding, $referencePriceCalculator),
            $detector,
            $referencePriceCalculator
        );

        return $quantityPriceCalculator;
    }
}
