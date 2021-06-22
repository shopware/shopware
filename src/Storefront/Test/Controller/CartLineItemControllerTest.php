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
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\CartLineItemController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CartLineItemControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    /**
     * @before
     * @after
     */
    public function clearFlashBag(): void
    {
        $this->getFlashBag()->clear();
    }

    /**
     * @dataProvider productNumbers
     */
    public function testAddProductByNumber(string $productId, string $productNumber): void
    {
        $contextToken = Uuid::randomHex();

        $cartService = $this->getContainer()->get(CartService::class);
        $this->createProduct($productId, $productNumber);
        $request = $this->createRequest(['number' => $productNumber]);

        $salesChannelContext = $this->createSalesChannelContext($contextToken);
        $response = $this->getContainer()->get(CartLineItemController::class)->addProductByNumber($request, $salesChannelContext);

        $cartLineItem = $cartService->getCart($contextToken, $salesChannelContext)->getLineItems()->get($productId);

        static::assertArrayHasKey('success', $this->getFlashBag()->all());
        static::assertNotNull($cartLineItem);
        static::assertSame(200, $response->getStatusCode());
    }

    public function productNumbers(): array
    {
        return [
            [Uuid::randomHex(), 'test.123'],
            [Uuid::randomHex(), 'test 123'],
            [Uuid::randomHex(), 'test-123'],
            [Uuid::randomHex(), 'test_123'],
            [Uuid::randomHex(), 'testäüö123'],
            [Uuid::randomHex(), 'test/123'],
        ];
    }

    private function getLineItemAddPayload(string $productId): array
    {
        return [
            'redirectTo' => 'frontend.cart.offcanvas',
            'lineItems' => [
                $productId => [
                    'id' => $productId,
                    'referencedId' => $productId,
                    'type' => 'product',
                    'stackable' => 1,
                    'removable' => 1,
                    'quantity' => 1,
                ],
            ],
        ];
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
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];
        $this->getContainer()->get('product.repository')->create([$product], $context);
    }

    private function createSalesChannelContext(string $contextToken): SalesChannelContext
    {
        return $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            Defaults::SALES_CHANNEL
        );
    }

    private function createRequest(array $request = []): Request
    {
        $request = new Request([], $request);
        $request->setSession($this->getContainer()->get('session'));

        return $request;
    }
}
