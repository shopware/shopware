<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Integration\Calculation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\PromotionFixtureBuilder;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionIntegrationTestBehaviour;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionSetGroupCalculationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;
    use PromotionIntegrationTestBehaviour;

    protected EntityRepository $productRepository;

    protected CartService $cartService;

    protected EntityRepository $promotionRepository;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->promotionRepository = $this->getContainer()->get('promotion.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);

        $this->context = $this->getContext();
    }

    /**
     * This test verifies that we give correct percentage discounts if the
     * set group consists of different line items and custom quantities.
     * We have a package of 2 of the cheapest items.
     * We only have 2 different products in our cart with total quantity 3 (1x and 2x).
     * Our cheapest 2 items are 1x the item with quantity 1 and then only 1x
     * the item of the products with quantity 2.
     * We give 100% discount on that package, which means the customer has to
     * only pay the 1 product that is left.
     *
     * @group promotions
     *
     * @throws CartException
     */
    public function testPercentageOnMultipleItemsAndSubsetQuantities(): void
    {
        $container = $this->getContainer();
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();

        $code = 'BF' . Random::getAlphanumericString(5);

        // prepare promotion
        $this->createTestFixtureProduct($productId1, 65, 19, $container, $this->context);
        $this->createTestFixtureProduct($productId2, 30, 7, $container, $this->context);

        // prepare a percentage promotion with 100% OFF
        // with a set group of the 2 cheapest items.
        $promotionBuilder = $this->createPromotionFixtureBuilder($container)
            ->addSetGroup('COUNT', 2, 'PRICE_ASC')
            ->setCode($code)
            ->addDiscount(PromotionDiscountEntity::SCOPE_SET, PromotionDiscountEntity::TYPE_PERCENTAGE, 100.0, false, null);
        $cart = $this->getCart($promotionBuilder, $productId1, $productId2, $code);

        static::assertEquals(65.0, $cart->getPrice()->getPositionPrice(), 'Position Total Price is wrong');
        static::assertEquals(65.0, $cart->getPrice()->getTotalPrice(), 'Total Price is wrong');
        static::assertEquals(54.62, $cart->getPrice()->getNetPrice(), 'Net Price is wrong');
        static::assertEquals(10.38, $cart->getPrice()->getCalculatedTaxes()->getAmount(), 'Taxes are wrong');
    }

    /**
     * This test verifies that we give correct absolute discounts if the
     * set group consists of different line items and custom quantities.
     * We have a package of 2 of the cheapest items.
     * We only have 2 different products in our cart with total quantity 3 (1x and 2x).
     * Our cheapest 2 items are 1x the item with quantity 1 and then only 1x
     * the item of the products with quantity 2.
     * We give 50 EUR discount on that package, which means the customer has to
     * pay (product 1 + product 2 - 50) + product 2.
     *
     * @group promotions
     *
     * @throws CartException
     */
    public function testAbsoluteOnMultipleItemsAndSubsetQuantities(): void
    {
        $container = $this->getContainer();
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();

        $code = 'BF' . Random::getAlphanumericString(5);

        // prepare promotion
        $this->createTestFixtureProduct($productId1, 60, 19, $container, $this->context);
        $this->createTestFixtureProduct($productId2, 30, 19, $container, $this->context);

        // prepare a percentage promotion with 100% OFF
        // with a set group of the 2 cheapest items.
        $promotionBuilder = $this->createPromotionFixtureBuilder($container)
            ->addSetGroup('COUNT', 2, 'PRICE_ASC')
            ->setCode($code)
            ->addDiscount(PromotionDiscountEntity::SCOPE_SET, PromotionDiscountEntity::TYPE_ABSOLUTE, 50.0, false, null);
        $cart = $this->getCart($promotionBuilder, $productId1, $productId2, $code);

        // total is the sum of p1 + p2 minus the absolute + the last product
        $expectedTotal = (30 + 60 - 50) + 60;
        // net price is both prices of p1 and p2 minus 50 and their net value......+ the net price of the last product
        $expectedNetPrice = ((30 + 60 - 50) / 119 * 100) + (60 / 119 * 100);
        // taxes should be the difference
        $expectedTaxes = $expectedTotal - $expectedNetPrice;

        static::assertEquals($expectedTotal, $cart->getPrice()->getPositionPrice(), 'Position Total Price is wrong');
        static::assertEquals($expectedTotal, $cart->getPrice()->getTotalPrice(), 'Total Price is wrong');
        static::assertEquals(round($expectedNetPrice, 2), $cart->getPrice()->getNetPrice(), 'Net Price is wrong');
        static::assertEquals(round($expectedTaxes, 2), $cart->getPrice()->getCalculatedTaxes()->getAmount(), 'Taxes are wrong');
    }

    /**
     * This test verifies that we give correct absolute discounts if the
     * set group consists of different line items and custom quantities.
     * We have a package of 2 of the cheapest items.
     * We only have 2 different products in our cart with total quantity 3 (1x and 2x).
     * Our cheapest 2 items are 1x the item with quantity 1 and then only 1x
     * the item of the products with quantity 2.
     * We give 20 EUR fixed count on every product in the group, which means the customer has to
     * pay 20 EUR + 20 EUR + product 2.
     *
     * @group promotions
     *
     * @throws CartException
     */
    public function testFixedUnitPriceOnMultipleItemsAndSubsetQuantities(): void
    {
        $container = $this->getContainer();
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();

        $code = 'BF' . Random::getAlphanumericString(5);

        // prepare promotion
        $this->createTestFixtureProduct($productId1, 60, 19, $container, $this->context);
        $this->createTestFixtureProduct($productId2, 30, 19, $container, $this->context);

        // prepare a percentage promotion with 100% OFF
        // with a set group of the 2 cheapest items.
        $promotionBuilder = $this->createPromotionFixtureBuilder($container)
            ->addSetGroup('COUNT', 2, 'PRICE_ASC')
            ->setCode($code)
            ->addDiscount(PromotionDiscountEntity::SCOPE_SET, PromotionDiscountEntity::TYPE_FIXED_UNIT, 20.0, false, null);
        $cart = $this->getCart($promotionBuilder, $productId1, $productId2, $code);

        // total is the sum of p1 + p2 minus the absolute + the last product
        $expectedTotal = (20 + 20) + 60;
        // net price is both prices of p1 and p2 minus 50 and their net value......+ the net price of the last product
        $expectedNetPrice = ((20 + 20) / 119 * 100) + (60 / 119 * 100);
        // taxes should be the difference
        $expectedTaxes = $expectedTotal - $expectedNetPrice;

        static::assertEquals($expectedTotal, $cart->getPrice()->getPositionPrice(), 'Position Total Price is wrong');
        static::assertEquals($expectedTotal, $cart->getPrice()->getTotalPrice(), 'Total Price is wrong');
        static::assertEquals(round($expectedNetPrice, 2), $cart->getPrice()->getNetPrice(), 'Net Price is wrong');
        static::assertEquals(round($expectedTaxes, 2), $cart->getPrice()->getCalculatedTaxes()->getAmount(), 'Taxes are wrong');
    }

    /**
     * This test verifies that we give correct absolute discounts if the
     * set group consists of different line items and custom quantities.
     * We have a package of 2 of the cheapest items.
     * We only have 2 different products in our cart with total quantity 3 (1x and 2x).
     * Our cheapest 2 items are 1x the item with quantity 1 and then only 1x
     * the item of the products with quantity 2.
     * We give 50 EUR fixed price for the whole package, which means the customer has to
     * pay 50 EUR + product 2.
     *
     * @group promotions
     *
     * @throws CartException
     */
    public function testFixedPriceOnMultipleItemsAndSubsetQuantities(): void
    {
        $container = $this->getContainer();
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();

        $code = 'BF' . Random::getAlphanumericString(5);

        // prepare promotion
        $this->createTestFixtureProduct($productId1, 60, 19, $container, $this->context);
        $this->createTestFixtureProduct($productId2, 30, 19, $container, $this->context);

        // prepare a percentage promotion with 100% OFF
        // with a set group of the 2 cheapest items.
        $promotionBuilder = $this->createPromotionFixtureBuilder($container)
            ->addSetGroup('COUNT', 2, 'PRICE_ASC')
            ->setCode($code)
            ->addDiscount(PromotionDiscountEntity::SCOPE_SET, PromotionDiscountEntity::TYPE_FIXED, 50.0, false, null);
        $cart = $this->getCart($promotionBuilder, $productId1, $productId2, $code);

        // total is the sum of p1 + p2 minus the absolute + the last product
        $expectedTotal = 50 + 60;
        // net price is both prices of p1 and p2 minus 50 and their net value......+ the net price of the last product
        $expectedNetPrice = (50 / 119 * 100) + (60 / 119 * 100);
        // taxes should be the difference
        $expectedTaxes = $expectedTotal - $expectedNetPrice;

        static::assertEquals($expectedTotal, $cart->getPrice()->getPositionPrice(), 'Position Total Price is wrong');
        static::assertEquals($expectedTotal, $cart->getPrice()->getTotalPrice(), 'Total Price is wrong');
        static::assertEquals(round($expectedNetPrice, 2), $cart->getPrice()->getNetPrice(), 'Net Price is wrong');
        static::assertEquals(round($expectedTaxes, 2), $cart->getPrice()->getCalculatedTaxes()->getAmount(), 'Taxes are wrong');
    }

    protected function createPromotionFixtureBuilder(ContainerInterface $container): PromotionFixtureBuilder
    {
        return new PromotionFixtureBuilder(
            Uuid::randomHex(),
            $container->get(SalesChannelContextFactory::class),
            $container->get('promotion.repository'),
            $container->get('promotion_setgroup.repository'),
            $container->get('promotion_discount.repository')
        );
    }

    protected function getCart(
        PromotionFixtureBuilder $promotionBuilder,
        string $productId1,
        string $productId2,
        string $code
    ): Cart {
        $promotionBuilder->buildPromotion();

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add 3 items to our cart
        // the cheapest one 1x and 2x the other product
        $cart = $this->addProduct($productId1, 2, $cart, $this->cartService, $this->context);
        $cart = $this->addProduct($productId2, 1, $cart, $this->cartService, $this->context);

        // add our promotion
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        return $cart;
    }
}
