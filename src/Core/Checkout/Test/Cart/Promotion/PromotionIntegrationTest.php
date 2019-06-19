<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Builder\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\Processor\PromotionProcessor;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

class PromotionIntegrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var EntityRepositoryInterface
     */
    protected $promotionRepository;

    /**
     * @var \Shopware\Core\System\SalesChannel\SalesChannelContext
     */
    private $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->promotionRepository = $this->getContainer()->get('promotion.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
    }

    /**
     * This test verifies that our promotion components are really involved in our checkout.
     * We add a product to the cart and apply a code for a promotion with 100% discount.
     * Our cart should have a total value of 0,00 in the end.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function test100PercentageDiscount()
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add a new sample product
        $this->createProduct($productId, 29, 17);

        // add a new promotion black friday
        $this->createPromotion($promotionId, $code, 100);

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 10, $cart);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart);

        static::assertEquals(0.0, $cart->getPrice()->getPositionPrice());
        static::assertEquals(0.0, $cart->getPrice()->getTotalPrice());
        static::assertEquals(0.0, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that our promotion components are really involved in our checkout.
     * We add a product to the cart and apply a code for a promotion with 50% discount.
     * Our cart should have a total value of 15,00 in the end.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function test50PercentageDiscount()
    {
        $productId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add a new sample product
        $this->createProduct($productId, 30, 17);

        // add a new promotion black friday
        $this->createPromotion($promotionId, $code, 50);

        /** @var Cart $cart */
        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create product and add to cart
        $cart = $this->addProduct($productId, 1, $cart);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart);

        static::assertEquals(15, $cart->getPrice()->getPositionPrice());
        static::assertEquals(15, $cart->getPrice()->getTotalPrice());
        static::assertEquals(12.82, $cart->getPrice()->getNetPrice());
    }

    /**
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    private function addProduct(string $productId, int $quantity, Cart $cart): Cart
    {
        $factory = new ProductLineItemFactory();
        $product = $factory->create($productId, ['quantity' => $quantity]);

        return $this->cartService->add($cart, $product, $this->context);
    }

    /**
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    private function addPromotionCode(string $code, Cart $cart): Cart
    {
        $factory = new PromotionItemBuilder(PromotionProcessor::LINE_ITEM_TYPE);

        /** @var LineItem $promotion */
        $promotion = $factory->buildPlaceholderItem($code, $this->context->getContext()->getCurrencyPrecision());

        return $this->cartService->add($cart, $promotion, $this->context);
    }

    private function createProduct(string $productId, float $grossPrice, float $taxRate)
    {
        $this->productRepository->create(
            [
                [
                    'id' => $productId,
                    'productNumber' => $productId,
                    'stock' => 1,
                    'name' => 'Test',
                    'price' => ['gross' => $grossPrice, 'net' => 9, 'linked' => false],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => $taxRate, 'name' => 'with id'],
                ],
            ],
            $this->context->getContext()
        );
    }

    private function createPromotion(string $promotionId, string $code, float $percentage)
    {
        $this->promotionRepository->create(
            [
                [
                    'id' => $promotionId,
                    'name' => 'Black Friday',
                    'active' => true,
                    'code' => $code,
                    'useCodes' => true,
                    'salesChannels' => [
                        ['salesChannelId' => Defaults::SALES_CHANNEL, 'priority' => 1],
                    ],
                    'discounts' => [
                        [
                            'id' => Uuid::randomHex(),
                            'scope' => PromotionDiscountEntity::SCOPE_CART,
                            'type' => PromotionDiscountEntity::TYPE_PERCENTAGE,
                            'value' => $percentage,
                            'considerAdvancedRules' => false,
                        ],
                    ],
                ],
            ],
            $this->context->getContext()
        );
    }
}
