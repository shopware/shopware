<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\CartLineItemController;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

/**
 * @internal
 */
class CartLineItemControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    #[Before]
    #[After]
    public function clearFlashBag(): void
    {
        $this->getFlashBag()->clear();
    }

    #[DataProvider('productNumbers')]
    public function testAddAndDeleteProductByNumber(string $productId, string $productNumber, bool $available = true): void
    {
        $contextToken = Uuid::randomHex();

        $cartService = $this->getContainer()->get(CartService::class);
        if ($productId && $available) {
            $this->createProduct($productId, $productNumber);
        }
        $request = $this->createRequest(['number' => $productNumber]);

        $salesChannelContext = $this->createSalesChannelContext($contextToken);
        $response = $this->getContainer()->get(CartLineItemController::class)->addProductByNumber($request, $salesChannelContext);

        $cart = $cartService->getCart($contextToken, $salesChannelContext);

        $cartLineItem = $cart->getLineItems()->get($productId);

        $flashBagEntries = $this->getFlashBag()->all();

        if ($productId && $available) {
            static::assertArrayHasKey('success', $flashBagEntries);
            static::assertNotNull($cartLineItem);
        } else {
            static::assertArrayHasKey('danger', $flashBagEntries);
            static::assertSame($this->getContainer()->get('translator')->trans('error.productNotFound', ['%number%' => \strip_tags($productNumber)]), $flashBagEntries['danger'][0]);
            static::assertNull($cartLineItem);
        }
        static::assertSame(200, $response->getStatusCode());

        // Delete
        if ($productId === '') {
            return;
        }

        $response = $this->getContainer()->get(CartLineItemController::class)->deleteLineItem($cart, $productId, $request, $salesChannelContext);

        $cartLineItem = $cartService->getCart($contextToken, $salesChannelContext)->getLineItems()->get($productId);

        $flashBagEntries = $this->getFlashBag()->all();

        if ($available) {
            static::assertArrayHasKey('success', $flashBagEntries);
        } else {
            static::assertArrayHasKey('danger', $flashBagEntries);
        }
        static::assertNull($cartLineItem);

        static::assertSame(200, $response->getStatusCode());
    }

    #[DataProvider('productVariations')]
    public function testAddVariationProductByNumber(string $productId, string $productNumber, bool $containerProductHasChildren, bool $expected): void
    {
        $contextToken = Uuid::randomHex();
        $salesChannelContext = $this->createSalesChannelContext($contextToken);

        $request = $this->createRequest(['number' => $productNumber]);
        $cartService = $this->getContainer()->get(CartService::class);
        $this->createProduct($productId, 'productContainer', $containerProductHasChildren);

        /** @var CartLineItemController $controller */
        $controller = $this->getContainer()->get(CartLineItemController::class);
        $controller->setContainer($this->getContainer());

        $response = $controller->addProductByNumber($request, $salesChannelContext);

        $cart = $cartService->getCart($contextToken, $salesChannelContext);

        $cartLineItem = $cart->getLineItems()->first();

        $flashBag = $this->getFlashBag();

        if ($expected) {
            static::assertNotEmpty($flashBag->get('success'));
            static::assertNotNull($cartLineItem);
        } else {
            $flashes = $flashBag->get('danger');
            static::assertNotEmpty($flashes);
            static::assertSame($this->getContainer()->get('translator')->trans('error.productNotFound', ['%number%' => \strip_tags($productNumber)]), $flashes[0]);
            static::assertNull($cartLineItem);
        }
        static::assertSame(200, $response->getStatusCode());
    }

    public static function productVariations(): \Generator
    {
        yield 'container product with children' => [
            Uuid::randomHex(),
            'productContainer',
            true,
            false,
        ];

        yield 'product without children' => [
            Uuid::randomHex(),
            'productContainer',
            false,
            true,
        ];

        yield 'existing product variation' => [
            Uuid::randomHex(),
            'child42',
            true,
            true,
        ];

        yield 'not existing product variation' => [
            Uuid::randomHex(),
            'child42',
            false,
            false,
        ];
    }

    /**
     * @return array<int, array<int, bool|string>>
     */
    public static function productNumbers(): array
    {
        return [
            [Uuid::randomHex(), 'test.123'],
            [Uuid::randomHex(), 'test 123'],
            [Uuid::randomHex(), 'test-123'],
            [Uuid::randomHex(), 'test_123'],
            [Uuid::randomHex(), 'testäüö123'],
            [Uuid::randomHex(), 'test/123'],
            [Uuid::randomHex(), 'test/unavailableProduct', false],
            ['', 'nonExisting'],
            ['', 'with<br>HTML'],
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function promotions(): array
    {
        return [
            ['testCode'],
        ];
    }

    #[DataProvider('promotions')]
    public function testAddPromotion(string $code): void
    {
        $contextToken = Uuid::randomHex();

        $cartService = $this->getContainer()->get(CartService::class);
        $request = $this->createRequest(['code' => $code]);

        $salesChannelContext = $this->createSalesChannelContext($contextToken);
        $this->getContainer()->get(CartLineItemController::class)->addPromotion(
            $cartService->getCart($contextToken, $salesChannelContext),
            $request,
            $salesChannelContext
        );

        $flashBagEntries = $this->getFlashBag()->all();

        static::assertArrayHasKey('danger', $flashBagEntries);
        static::assertSame($this->getContainer()->get('translator')->trans('checkout.promotion-not-found', ['%code%' => \strip_tags($code)]), $flashBagEntries['danger'][0]);
        static::assertCount(0, $cartService->getCart($contextToken, $salesChannelContext)->getLineItems());
    }

    private function getFlashBag(): FlashBag
    {
        /** @var FlashBag $sessionBag */
        $sessionBag = $this->getSession()->getBag('flashes');

        return $sessionBag;
    }

    private function createProduct(string $productId, string $productNumber, bool $hasChildren = false): void
    {
        $context = Context::createDefaultContext();
        /** @var string $taxId */
        $taxId = $this->getContainer()->get('tax.repository')->searchIds(new Criteria(), $context)->firstId();

        $product = [
            'id' => $productId,
            'name' => 'Test product',
            'productNumber' => $productNumber,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15.99, 'net' => 10, 'linked' => false],
            ],
            'taxId' => $taxId,
            'categories' => [
                ['id' => $productId, 'name' => 'Test category'],
            ],
            'visibilities' => [
                [
                    'id' => $productId,
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];
        if ($hasChildren) {
            $childId = Uuid::randomHex();
            $product['children'] = [
                [
                    'id' => $childId,
                    'name' => 'Test product',
                    'productNumber' => 'child42',
                    'stock' => 1,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 15.99, 'net' => 10, 'linked' => false],
                    ],
                    'taxId' => $taxId,
                    'categories' => [
                        ['id' => $childId, 'name' => 'Test category'],
                    ],
                    'visibilities' => [
                        [
                            'id' => $childId,
                            'salesChannelId' => TestDefaults::SALES_CHANNEL,
                            'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                        ],
                    ],
                ],
            ];
        }
        $this->getContainer()->get('product.repository')->create([$product], $context);
    }

    private function createSalesChannelContext(string $contextToken, ?string $paymentMethodId = null): SalesChannelContext
    {
        return $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::SALES_CHANNEL,
            $paymentMethodId ? [SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId] : []
        );
    }

    /**
     * @param array<string, string> $request
     */
    private function createRequest(array $request = []): Request
    {
        $request = new Request([], $request);
        $request->setSession($this->getSession());

        $requestStack = $this->getContainer()->get('request_stack');
        $requestStack->push($request);

        return $request;
    }
}
