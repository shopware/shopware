<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\CartLineItemController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
class CartLineItemControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    /**
     * @before
     *
     * @after
     */
    public function clearFlashBag(): void
    {
        $this->getFlashBag()->clear();
    }

    /**
     * @dataProvider productNumbers
     */
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
        $flashBag = $this->getFlashBag()->all();
        if ($productId && $available) {
            static::assertArrayHasKey('success', $flashBag);
            static::assertNotNull($cartLineItem);
        } else {
            static::assertArrayHasKey('danger', $flashBag);
            static::assertSame($this->getContainer()->get('translator')->trans('error.productNotFound', ['%number%' => \strip_tags($productNumber)]), $flashBag['danger'][0]);
            static::assertNull($cartLineItem);
        }
        static::assertSame(200, $response->getStatusCode());

        // Delete
        if ($productId === '') {
            return;
        }

        $response = $this->getContainer()->get(CartLineItemController::class)->deleteLineItem($cart, $productId, $request, $salesChannelContext);

        $cartLineItem = $cartService->getCart($contextToken, $salesChannelContext)->getLineItems()->get($productId);
        $flashBag = $this->getFlashBag()->all();

        if ($available) {
            static::assertArrayHasKey('success', $flashBag);
        } else {
            static::assertArrayHasKey('danger', $flashBag);
        }
        static::assertNull($cartLineItem);

        static::assertSame(200, $response->getStatusCode());
    }

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

    public static function promotions(): array
    {
        return [
            ['testCode'],
            ['with<br>HTML'],
        ];
    }

    /**
     * @dataProvider promotions
     */
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

        $flashBag = $this->getFlashBag()->all();
        static::assertArrayHasKey('danger', $flashBag);
        static::assertSame($this->getContainer()->get('translator')->trans('checkout.promotion-not-found', ['%code%' => \strip_tags($code)]), $flashBag['danger'][0]);
        static::assertCount(0, $cartService->getCart($contextToken, $salesChannelContext)->getLineItems());
    }

    private function getFlashBag(): FlashBagInterface
    {
        $request = $this->getContainer()->get('request_stack')->getMainRequest();
        if ($request === null) {
            $request = new Request();
            $request->setSession(new Session(new MockArraySessionStorage()));

            $this->getContainer()->get('request_stack')->push($request);
        }

        $session = $request->getSession();

        \assert($session instanceof Session);

        return $session->getFlashBag();
    }

    private function createProduct(string $productId, string $productNumber): void
    {
        $taxId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $product = [
            'id' => $productId,
            'name' => 'Test product',
            'productNumber' => $productNumber,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15.99, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['id' => $taxId, 'name' => 'testTaxRate', 'taxRate' => 15],
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

    private function createRequest(array $request = []): Request
    {
        $request = new Request([], $request);
        $request->setSession($this->getSession());

        return $request;
    }
}
