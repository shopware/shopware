<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Document\Event\DeliveryNoteOrdersEvent;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class DeliveryNoteRendererTest extends TestCase
{
    use DocumentTrait;
    use SnapshotTesting;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private DeliveryNoteRenderer $deliveryNoteRenderer;

    private CartService $cartService;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
            ]
        );

        $this->salesChannelContext->setRuleIds([$priceRuleId]);
        $this->deliveryNoteRenderer = static::getContainer()->get(DeliveryNoteRenderer::class);
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->productRepository = static::getContainer()->get('product.repository');
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(Translator::class)->reset();
        parent::tearDown();
    }

    public function testDocumentSnapshot(): void
    {
        $translator = static::getContainer()->get(Translator::class);
        $translator->injectSettings(
            $this->salesChannelContext->getSalesChannelId(),
            $this->salesChannelContext->getLanguageId(),
            'en-GB',
            $this->salesChannelContext->getContext()
        );

        $cart = $this->generateDemoCart(1);
        $orderId = $this->persistCart($cart);

        static::getContainer()->get('order.repository')->update([
            [
                'id' => $orderId,
                'orderDateTime' => '2023-11-24T12:00:00+00:00',
            ],
        ], $this->context);

        $operation = new DocumentGenerateOperation($orderId, HtmlRenderer::FILE_EXTENSION, [
            'documentComment' => '<script></script>This is a delivery note.',
            'custom' => [
                'deliveryDate' => '2023-11-24T12:00:00+00:00',
            ],
            'itemsPerPage' => 10,
            'displayHeader' => true,
            'displayFooter' => true,
            'displayPrices' => true,
            'displayPageCount' => true,
            'displayCompanyAddress' => true,
            'displayReturnAddress' => true,
            'companyName' => 'Example Company',
            'documentDate' => '2023-11-24T12:00:00+00:00',
        ]);

        $processedTemplate = $this->deliveryNoteRenderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig()
        );

        $rendered = $processedTemplate->getSuccess()[$orderId];
        static::assertInstanceOf(RenderedDocument::class, $rendered);

        $content = $rendered->getContent();

        // replace the date in the meta tag to avoid snapshot differences
        $processedHtml = preg_replace(
            '/(<meta name="date" content=")(.*?)(")/i',
            '$1[date]$3',
            $content
        );
        static::assertIsString($processedHtml);

        $this->assertHtmlSnapshot(
            'delivery_note_renderer_default',
            $processedHtml
        );
    }

    #[DataProvider('deliveryNoteRendererDataProvider')]
    public function testRender(string $deliveryNoteNumber, \Closure $assertionCallback): void
    {
        $cart = $this->generateDemoCart(3);

        $orderId = $this->cartService->order($cart, $this->salesChannelContext, new RequestDataBag());

        $operation = new DocumentGenerateOperation($orderId, HtmlRenderer::FILE_EXTENSION, [
            'documentNumber' => $deliveryNoteNumber,
            'itemsPerPage' => 2,
            'fileTypes' => [PdfRenderer::FILE_EXTENSION, HtmlRenderer::FILE_EXTENSION],
        ]);

        $caughtEvent = null;

        static::getContainer()->get('event_dispatcher')
            ->addListener(DeliveryNoteOrdersEvent::class, function (DeliveryNoteOrdersEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            });

        $processedTemplate = $this->deliveryNoteRenderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertInstanceOf(DeliveryNoteOrdersEvent::class, $caughtEvent);
        static::assertCount(1, $caughtEvent->getOperations());
        static::assertSame($operation, $caughtEvent->getOperations()[$orderId] ?? null);
        static::assertCount(1, $caughtEvent->getOrders());
        static::assertArrayHasKey($orderId, $processedTemplate->getSuccess());
        $rendered = $processedTemplate->getSuccess()[$orderId];
        $order = $caughtEvent->getOrders()->get($orderId);
        static::assertNotNull($order);

        static::assertInstanceOf(RenderedDocument::class, $rendered);
        static::assertCount(1, $caughtEvent->getOrders());
        static::assertStringContainsString('<html lang="en-GB">', $rendered->getContent());
        static::assertStringContainsString('</html>', $rendered->getContent());

        $assertionCallback($deliveryNoteNumber, $order->getOrderNumber(), $rendered);
    }

    public static function deliveryNoteRendererDataProvider(): \Generator
    {
        yield 'render delivery_note successfully' => [
            '2000',
            function (string $deliveryNoteNumber, string $orderNumber, RenderedDocument $rendered): void {
                $html = $rendered->getContent();
                static::assertStringContainsString('<html lang="en-GB">', $html);
                static::assertStringContainsString('</html>', $html);

                static::assertStringContainsString('Delivery note ' . $deliveryNoteNumber, $html);
                static::assertStringContainsString(\sprintf('Delivery note %s for Order %s ', $deliveryNoteNumber, $orderNumber), $html);
            },
        ];

        yield 'render delivery_note with document number' => [
            'DELIVERY_NOTE_9999',
            function (string $deliveryNoteNumber, string $orderNumber, RenderedDocument $rendered): void {
                static::assertSame('DELIVERY_NOTE_9999', $rendered->getNumber());
                static::assertSame('delivery_note_DELIVERY_NOTE_9999', $rendered->getName());

                static::assertStringContainsString("Delivery note $deliveryNoteNumber for Order $orderNumber", $rendered->getContent());
                static::assertStringContainsString("Delivery note $deliveryNoteNumber for Order $orderNumber", $rendered->getContent());
            },
        ];
    }

    public function testCreatingNewOrderVersionId(): void
    {
        $cart = $this->generateDemoCart(1);
        $orderId = $this->persistCart($cart);

        $operationDelivery = new DocumentGenerateOperation($orderId);

        static::assertSame($operationDelivery->getOrderVersionId(), Defaults::LIVE_VERSION);

        $this->deliveryNoteRenderer->render(
            [$orderId => $operationDelivery],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertNotSame($operationDelivery->getOrderVersionId(), Defaults::LIVE_VERSION);
    }

    private function generateDemoCart(int $productsCount): Cart
    {
        $cart = $this->cartService->createNew('A');

        $products = [];

        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());

        $ids = new IdsCollection();

        $lineItems = [];

        for ($i = 0; $i < $productsCount; ++$i) {
            $price = 100.0 + $i;
            $name = 'product ' . $i;
            $number = 'p' . $i;
            $tax = 19;

            $product = (new ProductBuilder($ids, $number))
                ->price($price)
                ->name($name)
                ->active(true)
                ->tax('test-tax', $tax)
                ->visibility()
                ->build();

            $products[] = $product;

            $lineItems[] = $factory->create(['id' => $ids->get($number), 'referencedId' => $ids->get($number)], $this->salesChannelContext);
            $this->addTaxDataToSalesChannel($this->salesChannelContext, $product['tax']);
        }

        $this->productRepository->create($products, $this->context);

        return $this->cartService->add($cart, $lineItems, $this->salesChannelContext);
    }
}
