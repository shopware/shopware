<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\Renderer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\Event\StornoOrdersEvent;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Test\Document\DocumentTrait;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('customer-order')]
class StornoRendererTest extends TestCase
{
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private EntityRepository $productRepository;

    private StornoRenderer $stornoRenderer;

    private CartService $cartService;

    private DocumentGenerator $documentGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();

        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
            ]
        );

        $this->salesChannelContext->setRuleIds([$priceRuleId]);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->stornoRenderer = $this->getContainer()->get(StornoRenderer::class);
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);
    }

    /**
     * @dataProvider stornoNoteRendererDataProvider
     *
     * @param array<string, string> $additionalConfig
     */
    public function testRender(array $additionalConfig, \Closure $assertionCallback): void
    {
        $cart = $this->generateDemoCart([7, 31]);
        $orderId = $this->cartService->order($cart, $this->salesChannelContext, new RequestDataBag());

        $invoiceConfig = new DocumentConfiguration();
        $invoiceConfig->setDocumentNumber('1001');

        $operationInvoice = new DocumentGenerateOperation($orderId, FileTypes::PDF, $invoiceConfig->jsonSerialize());

        $result = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $operationInvoice], $this->context)->getSuccess()->first();
        static::assertNotNull($result);
        $invoiceId = $result->getId();

        $config = [
            'displayLineItems' => true,
            'itemsPerPage' => 10,
            'displayFooter' => true,
            'displayHeader' => true,
        ];

        if (!empty($additionalConfig)) {
            $config = array_merge($config, $additionalConfig);
        }

        $operation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::PDF,
            $config,
            $invoiceId
        );

        $caughtEvent = null;

        $this->getContainer()->get('event_dispatcher')
            ->addListener(StornoOrdersEvent::class, function (StornoOrdersEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            });

        $processedTemplate = $this->stornoRenderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertInstanceOf(StornoOrdersEvent::class, $caughtEvent);
        static::assertCount(1, $caughtEvent->getOperations());
        static::assertSame($operation, $caughtEvent->getOperations()[$orderId] ?? null);
        static::assertCount(1, $caughtEvent->getOrders());
        $order = $caughtEvent->getOrders()->get($orderId);
        static::assertNotNull($order);
        static::assertInstanceOf(RendererResult::class, $processedTemplate);
        static::assertArrayHasKey($orderId, $processedTemplate->getSuccess());
        $rendered = $processedTemplate->getSuccess()[$orderId];
        static::assertInstanceOf(RenderedDocument::class, $rendered);
        static::assertStringContainsString('<html>', $rendered->getHtml());
        static::assertStringContainsString('</html>', $rendered->getHtml());

        $assertionCallback($rendered);
    }

    public function testRenderWithoutInvoice(): void
    {
        $cart = $this->generateDemoCart([7, 13]);
        $orderId = $this->cartService->order($cart, $this->salesChannelContext, new RequestDataBag());

        $operation = new DocumentGenerateOperation($orderId);

        $processedTemplate = $this->stornoRenderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertEmpty($processedTemplate->getSuccess());
        static::assertNotEmpty($errors = $processedTemplate->getErrors());
        static::assertArrayHasKey($orderId, $errors);
        static::assertInstanceOf(DocumentGenerationException::class, $errors[$orderId]);
        static::assertEquals(
            "Unable to generate document. Can not generate storno document because no invoice document exists. OrderId: $orderId",
            ($errors[$orderId]->getMessage())
        );
    }

    public static function stornoNoteRendererDataProvider(): \Generator
    {
        yield 'render storno successfully' => [
            [
                'documentNumber' => '1000',
                'custom' => [
                    'stornoNumber' => '1000',
                    'invoiceNumber' => '1001',
                ],
            ],
            function (?RenderedDocument $rendered = null): void {
                static::assertNotNull($rendered);
                static::assertStringContainsString('Cancellation no. 1000', $rendered->getHtml());
                static::assertStringContainsString('Cancellation 1000 for Invoice 1001', $rendered->getHtml());
            },
        ];

        yield 'render storno with document number' => [
            [
                'documentNumber' => 'STORNO_9999',
            ],
            function (?RenderedDocument $rendered = null): void {
                static::assertNotNull($rendered);
                static::assertEquals('STORNO_9999', $rendered->getNumber());
                static::assertEquals('cancellation_invoice_STORNO_9999', $rendered->getName());
            },
        ];
    }

    public function testUsingTheSameOrderVersionIdWithReferenceDocument(): void
    {
        $cart = $this->generateDemoCart([7]);
        $orderId = $this->persistCart($cart);

        $invoiceConfig = new DocumentConfiguration();
        $invoiceConfig->setDocumentNumber('1001');

        $operationInvoice = new DocumentGenerateOperation($orderId, FileTypes::PDF, $invoiceConfig->jsonSerialize());

        $result = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $operationInvoice], $this->context)->getSuccess()->first();
        static::assertNotNull($result);

        $operationStorno = new DocumentGenerateOperation($orderId);

        static::assertEquals($operationStorno->getOrderVersionId(), Defaults::LIVE_VERSION);
        static::assertTrue($this->orderVersionExists($orderId, $operationStorno->getOrderVersionId()));

        $this->stornoRenderer->render(
            [$orderId => $operationStorno],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertEquals($operationInvoice->getOrderVersionId(), $operationStorno->getOrderVersionId());
        static::assertTrue($this->orderVersionExists($orderId, $operationStorno->getOrderVersionId()));
    }

    /**
     * @param array<int, int> $taxes
     */
    private function generateDemoCart(array $taxes): Cart
    {
        $cart = $this->cartService->createNew('A');

        $keywords = ['awesome', 'epic', 'high quality'];

        $products = [];

        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());

        $ids = new IdsCollection();

        $lineItems = [];

        foreach ($taxes as $tax) {
            $price = random_int(100, 200000) / 100.0;

            shuffle($keywords);
            $name = ucfirst(implode(' ', $keywords) . ' product');

            $number = Uuid::randomHex();

            $product = (new ProductBuilder($ids, $number))
                ->price($price)
                ->name($name)
                ->active(true)
                ->tax('test-' . Uuid::randomHex(), $tax)
                ->visibility()
                ->build();

            $products[] = $product;

            $lineItems[] = $factory->create(['id' => $ids->get($number), 'referencedId' => $ids->get($number)], $this->salesChannelContext);
            $this->addTaxDataToSalesChannel($this->salesChannelContext, $product['tax']);
        }

        $this->productRepository->create($products, Context::createDefaultContext());

        return $this->cartService->add($cart, $lineItems, $this->salesChannelContext);
    }
}
