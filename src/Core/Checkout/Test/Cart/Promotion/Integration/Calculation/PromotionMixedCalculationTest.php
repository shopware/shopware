<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Integration\Calculation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionSetGroupTestFixtureBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionMixedCalculationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;
    use PromotionIntegrationTestBehaviour;
    use PromotionSetGroupTestFixtureBehaviour;

    protected EntityRepository $productRepository;

    protected CartService $cartService;

    protected EntityRepository $promotionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->promotionRepository = $this->getContainer()->get('promotion.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);
    }

    /**
     * This test verifies that we get a correct 0,00 final price if we
     * add an absolute promotion of -10 and an additional 100% discount.
     *
     * @group promotions
     *
     * @throws CartException
     */
    public function testMixedAbsoluteAndPercentageDiscount(): void
    {
        $productId = Uuid::randomHex();
        $promotionId1 = Uuid::randomHex();
        $promotionId2 = Uuid::randomHex();
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 60, 19, $this->getContainer(), $context);

        // add our existing promotions
        $this->createTestFixtureAbsolutePromotion($promotionId1, 'sale', 20, $this->getContainer());
        $this->createTestFixturePercentagePromotion($promotionId2, '100off', 100, null, $this->getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 5, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode('sale', $cart, $this->cartService, $context);
        $cart = $this->addPromotionCode('100off', $cart, $this->cartService, $context);

        static::assertEquals(0.0, $cart->getPrice()->getTotalPrice());
        static::assertEquals(0.0, $cart->getPrice()->getPositionPrice());
        static::assertEquals(0.0, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that we can successfully remove an added
     * promotion by code and get the original price again.
     *
     * @group promotions
     *
     * @throws CartException
     */
    public function testRemoveDiscountByCode(): void
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        // add a new sample product
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $code, 100, null, $this->getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // add product to cart
        $cart = $this->addProduct($productId, 1, $cart, $this->cartService, $context);

        // add promotion to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        // and remove again
        $cart = $this->removePromotionCode($code, $cart, $this->cartService, $context);

        static::assertEquals(119.0, $cart->getPrice()->getTotalPrice());
        static::assertEquals(119.0, $cart->getPrice()->getPositionPrice());
        static::assertEquals(100.0, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that our tax calculation uses the correct distribution of taxes.
     * This should be based on a proportional distribution of the actual quantities
     * that have to be discounted and not the one from the original quantities in the cart.
     * We have 2 items with qty 5 and 3. Due to our group, we have discount quantities of 2 and 3 (group sum should be 5).
     * This quantities have different proportional distribution compared to the original.
     * All our calculators need to use this new distribution within their price collection entries
     * when calculating the price and thus, the tax values.
     * The final assert will ensure that our 2 taxes have the correct values with the
     * new proportional distribution.
     *
     * @group promotions
     *
     * @throws CartException
     */
    public function testProportionalTaxDistribution(): void
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add 2 test products
        $this->createTestFixtureProduct($productId1, 119, 19, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($productId2, 107, 7, $this->getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 200, PromotionDiscountEntity::SCOPE_SET, $code, $this->getContainer(), $this->context);
        $this->createSetGroupFixture('COUNT', 5, 'PRICE_ASC', $promotionId, $this->getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create first product and add to cart
        $cart = $this->addProduct($productId1, 5, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($productId2, 3, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        /** @var CalculatedPrice $discountPrice */
        $discountPrice = $cart->getLineItems()->getFlat()[2]->getPrice();

        /** @var CalculatedTax $tax1 */
        $tax1 = $discountPrice->getCalculatedTaxes()->getElements()[19]->getTax();

        $tax2 = $discountPrice->getCalculatedTaxes()->getElements()[7]->getTax();

        // our correct values are based on a distribution of 2 + 3 instead of 5 + 3
        static::assertEquals(-24.4, $tax1);
        static::assertEquals(-13.49, $tax2);
    }

    /**
     * function tests that a promotion with two discount of type setGroup are correctly built and calculated
     * We also check here that only complete sets may be discounted. This means, that setGroups are not only considered as
     * precondition (and afterwards all matching groups will be discounted). They are the definition of the set and only
     * complete sets will be discounted
     * In this test it would be possible to discount a second setGroup2. Because we cannot build two complete setGroups,
     * only setGroup1 and setGroup2 is discounted once (the cheapest items because it is the standard sorting)
     *
     * @group promotions
     */
    public function testSetGroupDiscountOnlyOnCompleteSets(): void
    {
        $set1ProductId1 = Uuid::randomHex();
        $set1ProductId2 = Uuid::randomHex();
        $set2ProductId1 = Uuid::randomHex();
        $set2ProductId2 = Uuid::randomHex();

        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);

        // add 4 test products
        $this->createTestFixtureProduct($set1ProductId1, 10, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($set1ProductId2, 20, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($set2ProductId1, 30, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($set2ProductId2, 40, 10, $this->getContainer(), $this->context);

        // add 2 test rules
        $ruleId1 = $this->createRule('Group1Rule', [$set1ProductId1, $set1ProductId2], $this->getContainer());
        $ruleId2 = $this->createRule('Group2Rule', [$set2ProductId1, $set2ProductId2], $this->getContainer());

        $groupId1 = 'c5dd14614714432cb145a2642d80fd23';
        $groupId2 = 'c1fb8da6d041481c962a1a9f62639c87';

        // add a new promotion and two setGroup discounts
        $this->createTestFixtureSetGroupPromotion($promotionId, $code, $this->getContainer());
        $this->createSetGroupWithRuleFixture($groupId1, 'COUNT', 4, 'PRICE_ASC', $promotionId, $ruleId1, $this->getContainer());
        $this->createSetGroupWithRuleFixture($groupId2, 'COUNT', 4, 'PRICE_ASC', $promotionId, $ruleId2, $this->getContainer());
        $discountId1 = $this->createSetGroupDiscount($promotionId, 1, $this->getContainer(), 100, null);
        $discountId2 = $this->createSetGroupDiscount($promotionId, 2, $this->getContainer(), 100, null);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create first product and add to cart
        $cart = $this->addProduct($set1ProductId1, 2, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($set1ProductId2, 2, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($set2ProductId1, 4, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($set2ProductId2, 4, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertNotNull($cart->getLineItems()->get($discountId1));
        static::assertNotNull($cart->getLineItems()->get($discountId2));
        $group1DiscountPrice = $cart->getLineItems()->get($discountId1)->getPrice();
        $group2DiscountPrice = $cart->getLineItems()->get($discountId2)->getPrice();

        static::assertNotNull($group1DiscountPrice);
        static::assertNotNull($group2DiscountPrice);
        static::assertEquals(-120.0, $group1DiscountPrice->getTotalPrice(), 'Error in calculating expected discount for setGroup1');
        static::assertEquals(-60.0, $group2DiscountPrice->getTotalPrice(), 'Error in calculating expected discount for setGroup2');
    }

    /**
     * The Parameter buyminimum defines that there have to be bought a minimum of products to get a discount on the setGroup
     * we always add a count of 30 products to the cart where 10 products of 10 Euro are eligible and 10 products of 20 Euro
     * The 10 products of 50 euro in the cart are disallowed by the setgroup rule and therefore should be never
     * regarded for the discount
     * We may test here price sorting of the 20 products
     * e.g. picking the first, second third ... product
     * e.g. Applying maximum amount of discounted products
     * the percent rate
     * and the type of picking (vertical or horizontal)
     *
     * @dataProvider groupPackageAndPickerProvider
     *
     * @group promotions
     */
    public function testSetGroupPackageAndPickerCombinations(
        float $expectedDiscount,
        string $applyTo,
        string $maximumUsage,
        float $percentage,
        string $sorting,
        string $pickerKey,
        int $groupCount,
        string $groupSorting
    ): void {
        $setProductId1 = Uuid::randomHex();
        $setProductId2 = Uuid::randomHex();
        $fooProductId = Uuid::randomHex();

        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);

        // add 3 test products
        $this->createTestFixtureProduct($setProductId1, 10, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($setProductId2, 20, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($fooProductId, 50, 10, $this->getContainer(), $this->context);

        // add test rules
        $ruleId = $this->createRule('Group1Rule', [$setProductId1, $setProductId2], $this->getContainer());

        // add a new promotion and two setGroup discounts
        $this->createTestFixtureSetGroupPromotion($promotionId, $code, $this->getContainer());
        $this->createSetGroupWithRuleFixture(Uuid::randomHex(), 'COUNT', $groupCount, $groupSorting, $promotionId, $ruleId, $this->getContainer());

        $discountId = $this->createSetGroupDiscount(
            $promotionId,
            1,
            $this->getContainer(),
            $percentage,
            null,
            PromotionDiscountEntity::TYPE_PERCENTAGE,
            $sorting,
            $applyTo,
            $maximumUsage,
            $pickerKey
        );

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create first product and add to cart
        $cart = $this->addProduct($setProductId1, 10, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($setProductId2, 10, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($fooProductId, 10, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertNotNull($cart->getLineItems()->get($discountId));
        $group1DiscountPrice = $cart->getLineItems()->get($discountId)->getPrice();
        static::assertNotNull($group1DiscountPrice);
        static::assertEquals($expectedDiscount, $group1DiscountPrice->getTotalPrice());
    }

    /**
     * @return array<string, array<mixed>>
     *
     * expectedDiscount,
     * applyTo,
     * maximumUsage,
     * percentage,
     * sorting,
     * pickerKey,
     * groupCount,
     * groupSorting
     */
    public static function groupPackageAndPickerProvider(): array
    {
        return [
            /*
             * For every 4 items, get 1 first most expensive item
             * vertical => within each built group of items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             * groups are built sorted by cheapest items
             *
             * discount: 10% off
             */
            'Vertical, most expensive #1' => [
                -8.0, '1', '6', 10.0, 'PRICE_DESC', 'VERTICAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 second most expensive item
             * vertical => within each built group of items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             * groups are built sorted by cheapest items
             *
             * discount: 10% off
             */
            'Vertical, most expensive #2' => [
                -8.0, '2', '6', 10.0, 'PRICE_DESC', 'VERTICAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 third most expensive item
             * vertical => within each built group of items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Vertical, most expensive #3' => [
                -7.0, '3', '6', 10.0, 'PRICE_DESC', 'VERTICAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 forth most expensive item
             * vertical => within each built group of items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Vertical, most expensive #4' => [
                -7.0, '4', '6', 10.0, 'PRICE_DESC', 'VERTICAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 first most expensive item
             * horizontal => across all items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Horizontal, most expensive #1' => [
                -10.0, '1', '6', 10.0, 'PRICE_DESC', 'HORIZONTAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 second most expensive item
             * horizontal => across all items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Horizontal, most expensive #2' => [
                -10.0, '2', '6', 10.0, 'PRICE_DESC', 'HORIZONTAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 third most expensive item
             * horizontal => across all items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Horizontal, most expensive #3' => [
                -5.0, '3', '6', 10.0, 'PRICE_DESC', 'HORIZONTAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 forth most expensive item
             * horizontal => across all items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Horizontal, most expensive #4' => [
                -5.0, '4', '6', 10.0, 'PRICE_DESC', 'HORIZONTAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 first cheapest item
             * vertical => within each built group of items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Vertical, cheapest #1' => [
                -7.0, '1', '6', 10.0, 'PRICE_ASC', 'VERTICAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 second cheapest item
             * vertical => within each built group of items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Vertical, cheapest #2' => [
                -7.0, '2', '6', 10.0, 'PRICE_ASC', 'VERTICAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 third cheapest item
             * vertical => within each built group of items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Vertical, cheapest #3' => [
                -8.0, '3', '6', 10.0, 'PRICE_ASC', 'VERTICAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 forth cheapest item
             * vertical => within each built group of items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Vertical, cheapest #4' => [
                -8.0, '4', '6', 10.0, 'PRICE_ASC', 'VERTICAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 first cheapest item
             * horizontal => across all items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Horizontal, cheapest #1' => [
                -5.0, '1', '6', 10.0, 'PRICE_ASC', 'HORIZONTAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 second cheapest item
             * horizontal => across all items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Horizontal, cheapest #2' => [
                -5.0, '2', '6', 10.0, 'PRICE_ASC', 'HORIZONTAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 third cheapest item
             * horizontal => across all items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Horizontal, cheapest #3' => [
                -10.0, '3', '6', 10.0, 'PRICE_ASC', 'HORIZONTAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 4 items, get 1 forth cheapest item
             * horizontal => across all items
             * groups are built sorted by cheapest items
             * max 6 items may be discounted, because only 5 groups may be built, max discounted items is set to 5
             *
             * discount: 10% off
             */
            'Horizontal, cheapest #4' => [
                -10.0, '4', '6', 10.0, 'PRICE_ASC', 'HORIZONTAL', 4, 'PRICE_ASC',
            ],
            /*
             * For every 5 items, get 1 first most expensive item
             * vertical => within each built group of items
             * groups are built sorted by cheapest items
             * max 1 item is discounted
             *
             * discount: 10% off
             */
            'Vertical, most expensive #5' => [
                -1.0, '1', '1', 10.0, 'PRICE_DESC', 'VERTICAL', 5, 'PRICE_ASC',
            ],
            /*
             * For every 5 items, get 1 first most expensive item
             * vertical => within each built group of items
             * groups are built sorted by cheapest items
             * max 1 item is discounted
             *
             * discount: 10% off
             */
            'Vertical, cheapest #5' => [
                -1.0, '1', '1', 10.0, 'PRICE_ASC', 'VERTICAL', 5, 'PRICE_ASC',
            ],
            /*
             * For every 5 items, get 1 first most expensive item
             * horizontal => across all items
             * groups are built sorted by cheapest items
             * max 1 item is discounted
             *
             * discount: 10% off
             */
            'Horizontal, most expensive #5' => [
                -2.0, '1', '1', 10.0, 'PRICE_DESC', 'HORIZONTAL', 5, 'PRICE_ASC',
            ],
            /*
             * For every 5 items, get 1 first cheapest item
             * horizontal => across all items
             * groups are built sorted by cheapest items
             * max 1 item is discounted
             *
             * discount: 10% off
             */
            'Horizontal, cheapest #5' => [
                -1.0, '1', '1', 10.0, 'PRICE_ASC', 'HORIZONTAL', 5, 'PRICE_ASC',
            ],
            /*
             * For every 5 items, get 1 first most expensive item
             * vertical => within each built group of items
             * groups are built sorted by cheapest items
             * all possible items are discounted (up to 4, because only 4 groups may be built)
             *
             * discount: 10% off
             */
            'Vertical, most expensive #6' => [
                -6.0, '1', 'ALL', 10.0, 'PRICE_DESC', 'VERTICAL', 5, 'PRICE_ASC',
            ],
            /*
             * For every 5 items, get 1 first cheapest item
             * vertical => within each built group of items
             * groups are built sorted by cheapest items
             * all possible items are discounted (up to 4, because only 4 groups may be built)
             *
             * discount: 10% off
             */
            'Vertical, cheapest #6' => [
                -6.0, '1', 'ALL', 10.0, 'PRICE_ASC', 'VERTICAL', 5, 'PRICE_ASC',
            ],
            /*
             * For every 5 items, get 1 first most expensive item
             * horizontal => across all items
             * groups are built sorted by cheapest items
             * all possible items are discounted (up to 4, because only 4 groups may be built)
             *
             * discount: 10% off
             */
            'Horizontal, most expensive #6' => [
                -8.0, '1', 'ALL', 10.0, 'PRICE_DESC', 'HORIZONTAL', 5, 'PRICE_ASC',
            ],
            /*
             * For every 5 items, get 1 first cheapest item
             * horizontal => across all items
             * groups are built sorted by cheapest items
             * all possible items are discounted (up to 4, because only 4 groups may be built)
             *
             * discount: 10% off
             */
            'Horizontal, cheapest #6' => [
                -4.0, '1', 'ALL', 10.0, 'PRICE_ASC', 'HORIZONTAL', 5, 'PRICE_ASC',
            ],
            /*
             * Edge case for 'Vertical, most expensive #5' (here: group sorting desc)
             * For every 5 items, get 1 first most expensive item
             * vertical => within each built group of items
             * groups are built sorted by most expensive items
             * max 1 item is discounted
             *
             * discount: 10% off
             */
            'Vertical, most expensive #5-1' => [
                -2.0, '1', '1', 10.0, 'PRICE_DESC', 'VERTICAL', 5, 'PRICE_DESC',
            ],
            /*
             * Edge case for 'Vertical, cheapest #5-1' (here: group sorting desc)
             * For every 5 items, get 1 first most expensive item
             * vertical => within each built group of items
             * groups are built sorted by most expensive items
             * max 1 item is discounted
             *
             * discount: 10% off
             */
            'Vertical, cheapest #5-1' => [
                -2.0, '1', '1', 10.0, 'PRICE_ASC', 'VERTICAL', 5, 'PRICE_DESC',
            ],
        ];
    }

    /**
     * buy 3 t-shirts get first one free. Test vertical and horizontal picking
     *
     * @group promotions
     *
     * @dataProvider getBuyThreeTshirtsGetFirstOneFreeTestData
     */
    public function testBuy3TshirtsGetFirstOneFree(float $expectedDiscount, string $pickingType): void
    {
        $tshirt1 = Uuid::randomHex();
        $tshirt2 = Uuid::randomHex();
        $tshirt3 = Uuid::randomHex();
        $tshirt4 = Uuid::randomHex();
        $tshirt5 = Uuid::randomHex();
        $tshirt6 = Uuid::randomHex();
        $tshirt7 = Uuid::randomHex();
        $tshirt8 = Uuid::randomHex();
        $tshirt9 = Uuid::randomHex();

        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);

        // add 4 test products
        $this->createTestFixtureProduct($tshirt1, 5, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt2, 10, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt3, 15, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt4, 20, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt5, 25, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt6, 30, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt7, 35, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt8, 40, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt9, 45, 10, $this->getContainer(), $this->context);

        // add test rules
        $ruleId = $this->createRule('Group1Rule', [$tshirt1, $tshirt2, $tshirt3, $tshirt4, $tshirt5, $tshirt6, $tshirt7, $tshirt8, $tshirt9], $this->getContainer());

        // add a new promotion and two setGroup discounts
        $this->createTestFixtureSetGroupPromotion($promotionId, $code, $this->getContainer());
        $this->createSetGroupWithRuleFixture(Uuid::randomHex(), 'COUNT', 3, 'PRICE_ASC', $promotionId, $ruleId, $this->getContainer());
        $discountId = $this->createSetGroupDiscount(
            $promotionId,
            1,
            $this->getContainer(),
            100,
            null,
            PromotionDiscountEntity::TYPE_PERCENTAGE,
            'PRICE_ASC',
            '1',
            'ALL',
            $pickingType
        );

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create first product and add to cart
        $cart = $this->addProduct($tshirt1, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt2, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt3, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt4, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt5, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt6, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt7, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt8, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt9, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertNotNull($cart->getLineItems()->get($discountId));
        $groupDiscountPrice = $cart->getLineItems()->get($discountId)->getPrice();
        static::assertNotNull($groupDiscountPrice);

        static::assertEquals($expectedDiscount, $groupDiscountPrice->getTotalPrice());
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function getBuyThreeTshirtsGetFirstOneFreeTestData(): array
    {
        return [
            'Buy 3 t-shirts, get one free, horizontal picking' => [-(5.0 + 10.0 + 15.0), 'HORIZONTAL'],
            'Buy 3 t-shirts, get one free, vertical picking' => [-(5.0 + 20.0 + 35.0), 'VERTICAL'],
        ];
    }

    /**
     * buy 3 t-shirts get second one free. Test vertical and horizontal picking
     *
     * @group promotions
     *
     * @dataProvider getBuyThreeTshirtsGetSecondOneFreeTestData
     */
    public function testBuy3TshirtsGetSecondOneFree(float $expectedDiscount, string $pickingType): void
    {
        $tshirt1 = Uuid::randomHex();
        $tshirt2 = Uuid::randomHex();
        $tshirt3 = Uuid::randomHex();
        $tshirt4 = Uuid::randomHex();
        $tshirt5 = Uuid::randomHex();
        $tshirt6 = Uuid::randomHex();
        $tshirt7 = Uuid::randomHex();
        $tshirt8 = Uuid::randomHex();
        $tshirt9 = Uuid::randomHex();

        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);

        // add 4 test products
        $this->createTestFixtureProduct($tshirt1, 5, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt2, 10, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt3, 15, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt4, 20, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt5, 25, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt6, 30, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt7, 35, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt8, 40, 10, $this->getContainer(), $this->context);
        $this->createTestFixtureProduct($tshirt9, 45, 10, $this->getContainer(), $this->context);

        // add test rules
        $ruleId = $this->createRule('Group1Rule', [$tshirt1, $tshirt2, $tshirt3, $tshirt4, $tshirt5, $tshirt6, $tshirt7, $tshirt8, $tshirt9], $this->getContainer());

        // add a new promotion and two setGroup discounts
        $this->createTestFixtureSetGroupPromotion($promotionId, $code, $this->getContainer());
        $this->createSetGroupWithRuleFixture(Uuid::randomHex(), 'COUNT', 3, 'PRICE_ASC', $promotionId, $ruleId, $this->getContainer());
        $discountId = $this->createSetGroupDiscount(
            $promotionId,
            1,
            $this->getContainer(),
            100,
            null,
            PromotionDiscountEntity::TYPE_PERCENTAGE,
            'PRICE_ASC',
            '2',
            'ALL',
            $pickingType
        );

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create first product and add to cart
        $cart = $this->addProduct($tshirt1, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt2, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt3, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt4, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt5, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt6, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt7, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt8, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($tshirt9, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertNotNull($cart->getLineItems()->get($discountId));
        $groupDiscountPrice = $cart->getLineItems()->get($discountId)->getPrice();
        static::assertNotNull($groupDiscountPrice);

        static::assertEquals($expectedDiscount, $groupDiscountPrice->getTotalPrice());
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function getBuyThreeTshirtsGetSecondOneFreeTestData(): array
    {
        return [
            'Buy 3 t-shirts, get one free, horizontal picking' => [-(10.0 + 25.0 + 40.0), 'HORIZONTAL'],
            'Buy 3 t-shirts, get one free, vertical picking' => [-(20.0 + 25.0 + 30.0), 'VERTICAL'],
        ];
    }
}
