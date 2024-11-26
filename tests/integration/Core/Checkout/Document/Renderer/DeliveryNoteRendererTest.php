<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use Dompdf\Cpdf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Document\Event\DeliveryNoteOrdersEvent;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('checkout')]
class DeliveryNoteRendererTest extends TestCase
{
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private MockObject $templateRendererMock;

    private DeliveryNoteRenderer $deliveryNoteRenderer;

    private CartService $cartService;

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

        $this->templateRendererMock = $this->createMock(DocumentTemplateRenderer::class);
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->deliveryNoteRenderer = new DeliveryNoteRenderer(
            $this->getContainer()->get('order.repository'),
            $this->getContainer()->get(DocumentConfigLoader::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->templateRendererMock,
            $this->getContainer()->get(NumberRangeValueGeneratorInterface::class),
            $this->getContainer()->getParameter('kernel.project_dir'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(PdfRenderer::class)
        );
    }

    #[DataProvider('deliveryNoteRendererDataProvider')]
    public function testRender(string $deliveryNoteNumber, \Closure $assertionCallback): void
    {
        $cart = $this->generateDemoCart(3);

        $orderId = $this->cartService->order($cart, $this->salesChannelContext, new RequestDataBag());

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, [
            'documentNumber' => $deliveryNoteNumber,
            'itemsPerPage' => 2,
        ]);

        $caughtEvent = null;

        $this->getContainer()->get('event_dispatcher')
            ->addListener(DeliveryNoteOrdersEvent::class, function (DeliveryNoteOrdersEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            });

        $html = '';
        $this->renderedHtml($html);
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

        static::assertStringContainsString('<html>', $html);
        static::assertStringContainsString('</html>', $html);

        if (Feature::isActive('v6.7.0.0')) {
            $pdfVersion = Cpdf::PDF_VERSION;
            static::assertMatchesRegularExpression("/^%PDF-$pdfVersion/", $rendered->getContent());
        }

        $assertionCallback($deliveryNoteNumber, $html, $order->getOrderNumber(), $rendered);
    }

    public static function deliveryNoteRendererDataProvider(): \Generator
    {
        yield 'render delivery_note successfully' => [
            '2000',
            function (string $deliveryNoteNumber, string $html, string $orderNumber, RenderedDocument $rendered): void {
                static::assertStringContainsString('<html>', $html);
                static::assertStringContainsString('</html>', $html);

                if (Feature::isActive('v6.7.0.0')) {
                    $pdfVersion = Cpdf::PDF_VERSION;
                    static::assertMatchesRegularExpression("/^%PDF-$pdfVersion/", $rendered->getContent());
                }

                static::assertStringContainsString('Delivery note ' . $deliveryNoteNumber, $html);
                static::assertStringContainsString(\sprintf('Delivery note %s for Order %s ', $deliveryNoteNumber, $orderNumber), $html);
            },
        ];

        yield 'render delivery_note with document number' => [
            'DELIVERY_NOTE_9999',
            function (string $deliveryNoteNumber, string $html, string $orderNumber, RenderedDocument $rendered): void {
                static::assertEquals('DELIVERY_NOTE_9999', $rendered->getNumber());
                static::assertEquals('delivery_note_DELIVERY_NOTE_9999', $rendered->getName());

                static::assertStringContainsString("Delivery note $deliveryNoteNumber for Order $orderNumber", $html);
                static::assertStringContainsString("Delivery note $deliveryNoteNumber for Order $orderNumber", $html);
            },
        ];
    }

    public function testNotCreatingNewOrderVersionId(): void
    {
        $cart = $this->generateDemoCart(1);
        $orderId = $this->persistCart($cart);

        $operationDelivery = new DocumentGenerateOperation($orderId);

        static::assertEquals(Defaults::LIVE_VERSION, $operationDelivery->getOrderVersionId());

        $this->deliveryNoteRenderer->render(
            [$orderId => $operationDelivery],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertEquals(Defaults::LIVE_VERSION, $operationDelivery->getOrderVersionId());
    }

    private function renderedHtml(string &$html): void
    {
        $this->templateRendererMock
            ->method('render')
            ->willReturnCallback(function () use (&$html) {
                $html = $this->getContainer()
                    ->get(DocumentTemplateRenderer::class)
                    ->render(...\func_get_args());

                return $html;
            });
    }
}
