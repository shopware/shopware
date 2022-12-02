<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent;
use Shopware\Core\Checkout\Document\Event\InvoiceOrdersEvent;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\OrderDocumentCriteriaFactory;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Test\Document\DocumentTrait;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class InvoiceRendererTest extends TestCase
{
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private EntityRepository $productRepository;

    private InvoiceRenderer $invoiceRenderer;

    private CartService $cartService;

    private CurrencyFormatter $currencyFormatter;

    private string $deLanguageId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initServices();
    }

    /**
     * @dataProvider invoiceDataProvider
     *
     * @param array<int|string, int> $possibleTaxes
     */
    public function testRender(array $possibleTaxes, ?\Closure $beforeRenderHook, \Closure $assertionCallback): void
    {
        $cart = $this->generateDemoCart($possibleTaxes);
        $orderId = $this->persistCart($cart);

        $operationInvoice = new DocumentGenerateOperation($orderId);

        $caughtEvent = null;

        $this->getContainer()->get('event_dispatcher')
            ->addListener(InvoiceOrdersEvent::class, function (InvoiceOrdersEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            });

        if ($beforeRenderHook) {
            $beforeRenderHook($operationInvoice);
        }

        $processedTemplate = $this->invoiceRenderer->render(
            [$orderId => $operationInvoice],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertInstanceOf(InvoiceOrdersEvent::class, $caughtEvent);
        static::assertCount(1, $caughtEvent->getOrders());
        $order = $caughtEvent->getOrders()->get($orderId);
        static::assertNotNull($order);

        static::assertInstanceOf(RendererResult::class, $processedTemplate);
        if (!empty($processedTemplate->getSuccess())) {
            static::assertArrayHasKey($orderId, $processedTemplate->getSuccess());

            $rendered = $processedTemplate->getSuccess()[$orderId];

            static::assertNotNull($lineItems = $order->getLineItems());
            static::assertNotNull($firstLineItem = $lineItems->first());
            static::assertNotNull($lastLineItem = $lineItems->last());
            static::assertStringContainsString('<html>', $rendered->getHtml());
            static::assertStringContainsString('</html>', $rendered->getHtml());
            static::assertStringContainsString($firstLineItem->getLabel(), $rendered->getHtml());
            static::assertStringContainsString($lastLineItem->getLabel(), $rendered->getHtml());

            $assertionCallback($rendered, $order);
        } else {
            $assertionCallback($order->getId(), $processedTemplate->getErrors());
        }
    }

    public function invoiceDataProvider(): \Generator
    {
        $this->initServices();

        yield 'render with default language' => [
            [7],
            null,
            function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertNotNull($order->getCurrency());
                static::assertStringContainsString(
                    $this->currencyFormatter->formatCurrencyByLanguage(
                        $order->getAmountTotal(),
                        $order->getCurrency()->getIsoCode(),
                        $this->context->getLanguageId(),
                        $this->context
                    ),
                    $rendered->getHtml()
                );
            },
        ];

        yield 'render with different language' => [
            [7],
            function (DocumentGenerateOperation $operation): void {
                $this->getContainer()->get('order.repository')->upsert([[
                    'id' => $operation->getOrderId(),
                    'languageId' => $this->deLanguageId,
                ]], $this->context);

                $criteria = OrderDocumentCriteriaFactory::create([$operation->getOrderId()]);
                /** @var OrderEntity $order */
                $order = $this->getContainer()->get('order.repository')->search($criteria, $this->context)->get($operation->getOrderId());

                $context = clone $this->context;
                $context = $context->assign([
                    'languageIdChain' => array_unique(array_filter([$this->deLanguageId, $this->context->getLanguageId()])),
                ]);
                static::assertNotNull($order->getDeliveries());
                $this->getContainer()->get('shipping_method.repository')->upsert([[
                    'id' => $order->getDeliveries()->first()->getShippingMethod()->getId(),
                    'name' => 'DE express',
                ]], $context);
            },
            function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertNotNull($order->getCurrency());
                static::assertStringContainsString(
                    preg_replace('/\xc2\xa0/', ' ', $this->currencyFormatter->formatCurrencyByLanguage(
                        $order->getAmountTotal(),
                        $order->getCurrency()->getIsoCode(),
                        $this->deLanguageId,
                        $this->context
                    )) ?? '',
                    preg_replace('/\xc2\xa0/', ' ', $rendered->getHtml()) ?? ''
                );
                static::assertStringContainsString('DE express', preg_replace('/\xc2\xa0/', ' ', $rendered->getHtml()) ?? 'DE express');
            },
        ];

        yield 'render with syntax error' => [
            [7, 19, 22],
            function (): void {
                $this->addEventListener(
                    $this->getContainer()->get('event_dispatcher'),
                    DocumentTemplateRendererParameterEvent::class,
                    function (DocumentTemplateRendererParameterEvent $event): void {
                        throw new \RuntimeException('Errors happened while rendering');
                    }
                );
            },
            function (string $orderId, array $errors): void {
                static::assertNotEmpty($errors);
                static::assertArrayHasKey($orderId, $errors);
                static::assertEquals(
                    'Errors happened while rendering',
                    ($errors[$orderId]->getMessage())
                );
                $this->resetEventDispatcher();
            },
        ];

        yield 'render with different taxes' => [
            [7, 19, 22],
            null,
            function (RenderedDocument $rendered): void {
                foreach ([7, 19, 22] as $possibleTax) {
                    static::assertStringContainsString(
                        sprintf('plus %d%% VAT', $possibleTax),
                        $rendered->getHtml()
                    );
                }
            },
        ];

        yield 'render with shipping address' => [
            [7],
            function (DocumentGenerateOperation $operation): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);
                /** @var OrderEntity $order */
                $order = $this->getContainer()->get('order.repository')->search($criteria, $this->context)->get($orderId);
                static::assertNotNull($order->getDeliveries());
                $country = $order->getDeliveries()->getShippingAddress()->getCountries()->first();
                $country->setCompanyTax(new TaxFreeConfig(true, Defaults::CURRENCY, 0));

                $this->getContainer()->get('country.repository')->update([[
                    'id' => $country->getId(),
                    'companyTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
                ]], $this->context);
                $companyPhone = '123123123';
                $vatIds = ['VAT-123123'];

                static::assertNotNull($order->getOrderCustomer());
                $this->getContainer()->get('customer.repository')->update([[
                    'id' => $order->getOrderCustomer()->getCustomerId(),
                    'vatIds' => $vatIds,
                ]], $this->context);

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'itemsPerPage' => 10,
                        'displayFooter' => true,
                        'displayHeader' => true,
                        'executiveDirector' => true,
                        'displayDivergentDeliveryAddress' => true,
                        'companyPhone' => $companyPhone,
                        'intraCommunityDelivery' => true,
                        'displayAdditionalNoteDelivery' => true,
                        'deliveryCountries' => [$country->getId()],
                    ],
                ]);
            },
            function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertNotNull($orderDeliveries = $order->getDeliveries());
                static::assertNotNull($shippingAddresses = $orderDeliveries->getShippingAddress());
                $shippingAddress = $shippingAddresses->first();
                static::assertNotNull($shippingAddress);

                static::assertInstanceOf(RenderedDocument::class, $rendered);

                $rendered = $rendered->getHtml();

                static::assertStringContainsString('Shipping address', $rendered);
                static::assertStringContainsString($shippingAddress->getStreet(), $rendered);
                static::assertStringContainsString($shippingAddress->getCity(), $rendered);
                static::assertStringContainsString($shippingAddress->getFirstName(), $rendered);
                static::assertStringContainsString($shippingAddress->getLastName(), $rendered);
                static::assertStringContainsString($shippingAddress->getZipcode(), $rendered);
                static::assertStringContainsString('Intra-community delivery (EU)', $rendered);
                if (!Feature::isActive('v6.5.0.0')) {
                    static::assertStringContainsString('VAT-123123', $rendered);
                }
                static::assertStringContainsString('123123123', $rendered);
            },
        ];

        yield 'render with billing address' => [
            [7],
            function (DocumentGenerateOperation $operation): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                /** @var OrderEntity $order */
                $order = $this->getContainer()->get('order.repository')
                    ->search($criteria, $this->context)->get($orderId);

                static::assertNotNull($order->getOrderCustomer());
                $this->getContainer()->get('customer.repository')->update([[
                    'id' => $order->getOrderCustomer()->getCustomerId(),
                    'vatIds' => ['VAT-123123'],
                ]], $this->context);

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'displayFooter' => true,
                        'displayHeader' => true,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertInstanceOf(RenderedDocument::class, $rendered);

                static::assertNotNull($order->getAddresses());

                /** @var OrderAddressEntity $orderAddress */
                $orderAddress = $order->getAddresses()->first();
                $rendered = $rendered->getHtml();

                static::assertNotNull($orderAddress->getSalutation());
                static::assertNotNull($orderAddress->getCountry());
                static::assertNotNull($orderAddress->getCountry()->getName());
                static::assertNotNull($orderAddress->getSalutation()->getLetterName());
                static::assertNotNull($orderAddress->getSalutation()->getDisplayName());

                static::assertStringContainsString($orderAddress->getStreet(), $rendered);
                static::assertStringContainsString($orderAddress->getZipcode(), $rendered);
                static::assertStringContainsString($orderAddress->getCity(), $rendered);
                static::assertStringContainsString($orderAddress->getCountry()->getName(), $rendered);
                if (!Feature::isActive('v6.5.0.0')) {
                    static::assertStringNotContainsString($orderAddress->getSalutation()->getLetterName(), $rendered);
                    static::assertStringContainsString($orderAddress->getSalutation()->getDisplayName(), $rendered);
                }
            },
        ];

        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM customer');
    }

    public function testCreateNewOrderVersionId(): void
    {
        $cart = $this->generateDemoCart([7]);
        $orderId = $this->persistCart($cart);

        $operationInvoice = new DocumentGenerateOperation($orderId);

        static::assertEquals($operationInvoice->getOrderVersionId(), Defaults::LIVE_VERSION);
        static::assertTrue($this->orderVersionExists($orderId, $operationInvoice->getOrderVersionId()));

        $this->invoiceRenderer->render(
            [$orderId => $operationInvoice],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertNotEquals($operationInvoice->getOrderVersionId(), Defaults::LIVE_VERSION);
        static::assertTrue($this->orderVersionExists($orderId, $operationInvoice->getOrderVersionId()));
    }

    private function initServices(): void
    {
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
        $this->invoiceRenderer = $this->getContainer()->get(InvoiceRenderer::class);
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->currencyFormatter = $this->getContainer()->get(CurrencyFormatter::class);
        $this->deLanguageId = $this->getDeDeLanguageId();
    }

    /**
     * @param array<int|string, int> $taxes
     */
    private function generateDemoCart(array $taxes): Cart
    {
        $cart = $this->cartService->createNew('a-b-c', 'A');

        $keywords = ['awesome', 'epic', 'high quality'];

        $products = [];

        $factory = new ProductLineItemFactory();

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

            $lineItems[] = $factory->create($ids->get($number));
            $this->addTaxDataToSalesChannel($this->salesChannelContext, $product['tax']);
        }

        $this->productRepository->create($products, Context::createDefaultContext());

        return $this->cartService->add($cart, $lineItems, $this->salesChannelContext);
    }
}
