<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\Connection;
use Dompdf\Cpdf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent;
use Shopware\Core\Checkout\Document\Event\InvoiceOrdersEvent;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\OrderDocumentCriteriaFactory;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('checkout')]
class InvoiceRendererTest extends TestCase
{
    use AppSystemTestBehaviour;
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private EntityRepository $productRepository;

    private MockObject $templateRendererMock;

    private InvoiceRenderer $invoiceRenderer;

    private CartService $cartService;

    private static string $deLanguageId;

    private static ?\Closure $callback = null;

    protected function setUp(): void
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

        $this->templateRendererMock = $this->createMock(DocumentTemplateRenderer::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);
        self::$deLanguageId = $this->getDeDeLanguageId();

        $this->invoiceRenderer = new InvoiceRenderer(
            $this->getContainer()->get('order.repository'),
            $this->getContainer()->get(DocumentConfigLoader::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->templateRendererMock,
            $this->getContainer()->get(NumberRangeValueGeneratorInterface::class),
            $this->getContainer()->getParameter('kernel.project_dir'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(PdfRenderer::class),
        );
    }

    protected function tearDown(): void
    {
        if (self::$callback instanceof \Closure) {
            $this->getContainer()->get('event_dispatcher')->removeListener(DocumentTemplateRendererParameterEvent::class, self::$callback);
        }
    }

    /**
     * @param array<int|string, int> $possibleTaxes
     */
    #[DataProvider('invoiceDataProvider')]
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

        if ($beforeRenderHook instanceof \Closure) {
            $beforeRenderHook($operationInvoice, $this->getContainer());
        }

        $html = '';
        $this->renderedHtml($html);
        $processedTemplate = $this->invoiceRenderer->render(
            [$orderId => $operationInvoice],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertInstanceOf(InvoiceOrdersEvent::class, $caughtEvent);
        static::assertCount(1, $caughtEvent->getOperations());
        static::assertSame($operationInvoice, $caughtEvent->getOperations()[$orderId] ?? null);
        static::assertCount(1, $caughtEvent->getOrders());
        $order = $caughtEvent->getOrders()->get($orderId);
        static::assertNotNull($order);

        if ($processedTemplate->getSuccess() !== []) {
            static::assertArrayHasKey($orderId, $processedTemplate->getSuccess());

            /** @var RenderedDocument $rendered */
            $rendered = $processedTemplate->getSuccess()[$orderId];

            static::assertInstanceOf(OrderLineItemCollection::class, $lineItems = $order->getLineItems());
            static::assertInstanceOf(OrderLineItemEntity::class, $firstLineItem = $lineItems->first());
            static::assertInstanceOf(OrderLineItemEntity::class, $lastLineItem = $lineItems->last());

            static::assertStringContainsString('<html>', $html);
            static::assertStringContainsString('</html>', $html);
            static::assertStringContainsString($firstLineItem->getLabel(), $html);
            static::assertStringContainsString($lastLineItem->getLabel(), $html);

            if (Feature::isActive('v6.7.0.0')) {
                $pdfVersion = Cpdf::PDF_VERSION;
                static::assertMatchesRegularExpression("/^%PDF-$pdfVersion/", $rendered->getContent());
            }

            $assertionCallback($rendered, $html, $order, $this->getContainer());
        } else {
            $assertionCallback($order->getId(), $processedTemplate->getErrors());
        }
    }

    public static function invoiceDataProvider(): \Generator
    {
        $documentDate = new \DateTime();

        yield 'render with default language' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container) use ($documentDate): void {
                $operation->assign([
                    'config' => [
                        'displayHeader' => true,
                        'documentDate' => $documentDate,
                        'displayLineItems' => true,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, string $html, OrderEntity $order, ContainerInterface $container) use ($documentDate): void {
                static::assertNotNull($order->getCurrency());

                static::assertStringContainsString(
                    $container->get(CurrencyFormatter::class)->formatCurrencyByLanguage(
                        $order->getAmountTotal(),
                        $order->getCurrency()->getIsoCode(),
                        Context::createDefaultContext()->getLanguageId(),
                        Context::createDefaultContext(),
                    ),
                    $html
                );

                static::assertNotNull($order->getLanguage());
                static::assertNotNull($locale = $order->getLanguage()->getLocale());
                $formatter = new \IntlDateFormatter($locale->getCode(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
                $formattedDate = $formatter->format($documentDate);

                static::assertNotFalse($formattedDate);
                static::assertStringContainsString(
                    \sprintf('Date %s', $formattedDate),
                    $html
                );
            },
        ];

        yield 'render with different language' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container) use ($documentDate): void {
                $container->get('order.repository')->upsert([[
                    'id' => $operation->getOrderId(),
                    'languageId' => self::$deLanguageId,
                ]], Context::createDefaultContext());

                $criteria = OrderDocumentCriteriaFactory::create([$operation->getOrderId()]);
                $order = $container->get('order.repository')->search($criteria, Context::createDefaultContext())->get($operation->getOrderId());
                static::assertInstanceOf(OrderEntity::class, $order);

                $context = clone Context::createDefaultContext();
                $context = $context->assign([
                    'languageIdChain' => array_unique(array_filter([self::$deLanguageId, Context::createDefaultContext()->getLanguageId()])),
                ]);
                static::assertNotNull($order->getDeliveries());
                /** @var $delivery OrderDeliveryEntity */
                static::assertNotNull($delivery = $order->getDeliveries()->first());
                /** @var $shippingMethod ShippingMethodEntity */
                static::assertNotNull($shippingMethod = $delivery->getShippingMethod());

                $container->get('shipping_method.repository')->upsert([[
                    'id' => $shippingMethod->getId(),
                    'name' => 'DE express',
                ]], $context);

                $operation->assign([
                    'config' => [
                        'displayHeader' => true,
                        'documentDate' => $documentDate,
                        'displayLineItems' => true,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, string $html, OrderEntity $order, ContainerInterface $container) use ($documentDate): void {
                static::assertNotNull($order->getCurrency());

                static::assertStringContainsString(
                    preg_replace('/\xc2\xa0/', ' ', $container->get(CurrencyFormatter::class)->formatCurrencyByLanguage(
                        $order->getAmountTotal(),
                        $order->getCurrency()->getIsoCode(),
                        self::$deLanguageId,
                        Context::createDefaultContext(),
                    )) ?? '',
                    preg_replace('/\xc2\xa0/', ' ', $html) ?? ''
                );
                static::assertStringContainsString('DE express', preg_replace('/\xc2\xa0/', ' ', $html) ?? 'DE express');

                static::assertNotNull($order->getLanguage());
                static::assertNotNull($locale = $order->getLanguage()->getLocale());
                $formatter = new \IntlDateFormatter($locale->getCode(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
                $formattedDate = $formatter->format($documentDate);

                static::assertNotFalse($formattedDate);
                static::assertStringContainsString(
                    \sprintf('Datum %s', $formattedDate),
                    $html
                );
            },
        ];

        yield 'render with syntax error' => [
            [7, 19, 22],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                self::$callback = function (DocumentTemplateRendererParameterEvent $event): void {
                    throw new \RuntimeException('Errors happened while rendering');
                };

                $container->get('event_dispatcher')->addListener(DocumentTemplateRendererParameterEvent::class, self::$callback);
            },
            function (string $orderId, array $errors): void {
                static::assertNotNull(self::$callback);
                static::assertNotEmpty($errors);
                static::assertArrayHasKey($orderId, $errors);

                /** @var \RuntimeException $error */
                $error = $errors[$orderId];
                static::assertEquals(
                    'Errors happened while rendering',
                    $error->getMessage()
                );
            },
        ];

        yield 'render with different taxes' => [
            [7, 19, 22],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, string $html): void {
                foreach ([7, 19, 22] as $possibleTax) {
                    static::assertStringContainsString(
                        \sprintf('plus %d%% VAT', $possibleTax),
                        $html
                    );
                }
            },
        ];

        yield 'render with shipping address' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);
                $order = $container->get('order.repository')->search($criteria, Context::createDefaultContext())->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);
                static::assertNotNull($order->getDeliveries());
                /** @var CountryEntity $country */
                $country = $order->getDeliveries()->getShippingAddress()->getCountries()->first();
                $country->setCompanyTax(new TaxFreeConfig(true, Defaults::CURRENCY, 0));

                $container->get('country.repository')->update([[
                    'id' => $country->getId(),
                    'companyTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
                ]], Context::createDefaultContext());
                $companyPhone = '123123123';
                $vatIds = ['VAT-123123'];

                static::assertNotNull($order->getOrderCustomer());
                $container->get('customer.repository')->update([[
                    'id' => $order->getOrderCustomer()->getCustomerId(),
                    'vatIds' => $vatIds,
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'itemsPerPage' => 10,
                        'displayFooter' => true,
                        'displayHeader' => true,
                        'executiveDirector' => true,
                        'displayDivergentDeliveryAddress' => true,
                        'companyPhone' => $companyPhone,
                        'displayAdditionalNoteDelivery' => true,
                        'deliveryCountries' => [$country->getId()],
                    ],
                ]);
            },
            function (RenderedDocument $rendered, string $html, OrderEntity $order): void {
                static::assertNotNull($orderDeliveries = $order->getDeliveries());
                $shippingAddress = $orderDeliveries->getShippingAddress()->first();
                static::assertNotNull($shippingAddress);
                static::assertNotNull($shippingAddress->getZipcode());

                static::assertStringContainsString('Shipping address', $html);
                static::assertStringContainsString($shippingAddress->getStreet(), $html);
                static::assertStringContainsString($shippingAddress->getCity(), $html);
                static::assertStringContainsString($shippingAddress->getFirstName(), $html);
                static::assertStringContainsString($shippingAddress->getLastName(), $html);
                static::assertStringContainsString($shippingAddress->getZipcode(), $html);
                static::assertStringContainsString('123123123', $html);
            },
        ];

        yield 'render with billing address' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);

                static::assertNotNull($order->getOrderCustomer());
                $container->get('customer.repository')->update([[
                    'id' => $order->getOrderCustomer()->getCustomerId(),
                    'vatIds' => ['VAT-123123'],
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'displayFooter' => true,
                        'displayHeader' => true,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, string $html, OrderEntity $order): void {
                static::assertNotNull($order->getAddresses());

                /** @var OrderAddressEntity $orderAddress */
                $orderAddress = $order->getAddresses()->first();

                static::assertNotNull($orderAddress->getSalutation());
                static::assertNotNull($orderAddress->getCountry());
                static::assertNotNull($orderAddress->getCountry()->getName());
                static::assertNotNull($orderAddress->getSalutation()->getLetterName());
                static::assertNotNull($orderAddress->getSalutation()->getDisplayName());
                static::assertNotNull($orderAddress->getZipcode());

                static::assertStringContainsString($orderAddress->getStreet(), $html);
                static::assertStringContainsString($orderAddress->getZipcode(), $html);
                static::assertStringContainsString($orderAddress->getCity(), $html);
                static::assertStringContainsString($orderAddress->getCountry()->getName(), $html);
            },
        ];

        yield 'render customer VAT-ID with displayCustomerVatId is checked' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);

                static::assertNotNull($order->getOrderCustomer());
                $container->get('customer.repository')->update([[
                    'id' => $order->getOrderCustomer()->getCustomerId(),
                    'vatIds' => ['VAT-123123'],
                ]], Context::createDefaultContext());

                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getAddresses()->get($order->getBillingAddressId()));
                static::assertNotNull($order->getAddresses()->get($order->getBillingAddressId())->getCountry());
                $container->get('country.repository')->upsert([[
                    'id' => $order->getAddresses()->get($order->getBillingAddressId())->getCountry()->getId(),
                    'companyTax' => ['amount' => 0, 'enabled' => true, 'currencyId' => Context::createDefaultContext()->getCurrencyId()],
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'displayHeader' => true,
                        'displayCustomerVatId' => true,
                        'displayAdditionalNoteDelivery' => false,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, string $html, OrderEntity $order): void {
                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getOrderCustomer());

                /** @var CustomerEntity $customer */
                $customer = $order->getOrderCustomer()->getCustomer();

                static::assertNotNull($customer);
                static::assertNotNull($customer->getVatIds());

                $vatId = $customer->getVatIds()[0];

                static::assertStringContainsString("VAT Reg.No: $vatId", $html);
            },
        ];

        yield 'render customer VAT-ID with displayCustomerVatId unchecked' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);

                static::assertNotNull($order->getOrderCustomer());
                $container->get('customer.repository')->update([[
                    'id' => $order->getOrderCustomer()->getCustomerId(),
                    'vatIds' => ['VAT-123123'],
                ]], Context::createDefaultContext());

                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getAddresses()->get($order->getBillingAddressId()));
                static::assertNotNull($order->getAddresses()->get($order->getBillingAddressId())->getCountry());
                $container->get('country.repository')->upsert([[
                    'id' => $order->getAddresses()->get($order->getBillingAddressId())->getCountry()->getId(),
                    'companyTax' => ['amount' => 0, 'enabled' => true, 'currencyId' => Context::createDefaultContext()->getCurrencyId()],
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'displayHeader' => true,
                        'displayFooter' => false,
                        'displayCustomerVatId' => false,
                        'displayAdditionalNoteDelivery' => false,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, string $html, OrderEntity $order): void {
                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getOrderCustomer());

                /** @var CustomerEntity $customer */
                $customer = $order->getOrderCustomer()->getCustomer();

                static::assertNotNull($customer);
                static::assertNotNull($customer->getVatIds());

                static::assertStringNotContainsString('VAT Reg.No:', $html);
            },
        ];

        yield 'render with customer VAT-ID is null' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);

                static::assertNotNull($order->getOrderCustomer());
                $container->get('customer.repository')->update([[
                    'id' => $order->getOrderCustomer()->getCustomerId(),
                    'vatIds' => [],
                ]], Context::createDefaultContext());

                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getAddresses()->get($order->getBillingAddressId()));
                static::assertNotNull($order->getAddresses()->get($order->getBillingAddressId())->getCountry());
                $container->get('country.repository')->upsert([[
                    'id' => $order->getAddresses()->get($order->getBillingAddressId())->getCountry()->getId(),
                    'companyTax' => ['amount' => 0, 'enabled' => true, 'currencyId' => Context::createDefaultContext()->getCurrencyId()],
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'displayFooter' => false,
                        'displayHeader' => true,
                        'displayCustomerVatId' => true,
                        'displayAdditionalNoteDelivery' => true,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, string $html, OrderEntity $order): void {
                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getOrderCustomer());

                /** @var CustomerEntity $customer */
                $customer = $order->getOrderCustomer()->getCustomer();

                static::assertNotNull($customer);
                static::assertEmpty($customer->getVatIds());

                static::assertStringNotContainsString('VAT Reg.No:', $html);
            },
        ];
    }

    public function testCreateNewOrderVersionId(): void
    {
        $cart = $this->generateDemoCart([7]);
        $orderId = $this->persistCart($cart);

        $operationInvoice = new DocumentGenerateOperation($orderId);

        static::assertEquals(Defaults::LIVE_VERSION, $operationInvoice->getOrderVersionId());
        static::assertTrue($this->orderVersionExists($orderId, $operationInvoice->getOrderVersionId()));

        $this->getContainer()->get(InvoiceRenderer::class)
            ->render([$orderId => $operationInvoice], $this->context, new DocumentRendererConfig());

        static::assertNotEquals(Defaults::LIVE_VERSION, $operationInvoice->getOrderVersionId());
        static::assertTrue($this->orderVersionExists($orderId, $operationInvoice->getOrderVersionId()));
    }

    /**
     * @param array{accountType: string} $customerSettings
     * @param array{enableIntraCommunityDeliveryLabel: bool, setCustomerShippingCountryAsMemberCountry: bool} $invoiceSettings
     */
    #[DataProvider('invoiceDataProviderTestIntraCommunityDeliveryLabel')]
    public function testRenderDocumentDisplayOfIntraCommunityDeliveryLabel(
        array $customerSettings,
        array $invoiceSettings,
        bool $enableTaxFreeB2bOption,
        bool $expectedOutput
    ): void {
        $cart = $this->generateDemoCart([7]);
        $orderId = $this->persistCart($cart);
        $invoice = new DocumentGenerateOperation($orderId);

        $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

        $order = $this->getContainer()->get('order.repository')
            ->search($criteria, Context::createDefaultContext())->get($orderId);
        static::assertInstanceOf(OrderEntity::class, $order);

        if ($customerSettings) {
            $this->updateCustomer($order, $customerSettings);
        }

        if ($invoiceSettings) {
            $this->updateInvoiceConfig($invoiceSettings);
            $this->updateCountryMemberState($order, $invoiceSettings['setCustomerShippingCountryAsMemberCountry']);
        }

        if ($enableTaxFreeB2bOption) {
            $this->updateCountrySettings($order);
        }

        $html = '';
        $this->renderedHtml($html);
        $rendered = $this->invoiceRenderer->render(
            [$orderId => $invoice],
            $this->context,
            new DocumentRendererConfig()
        )->getOrderSuccess($orderId);

        static::assertNotNull($rendered);

        if ($expectedOutput) {
            static::assertStringContainsString('Intra-community delivery (EU)', $html);
        } else {
            static::assertStringNotContainsString('Intra-community delivery (EU)', $html);
        }
    }

    public static function invoiceDataProviderTestIntraCommunityDeliveryLabel(): \Generator
    {
        yield 'shall not be displayed' => [
            'customerSettings' => [],
            'invoiceSettings' => [],
            'enableTaxFreeB2bOption' => false,
            'expectedOutput' => false,
        ];

        yield 'shall be displayed cause all necessary options are set' => [
            'customerSettings' => [
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            ],
            'invoiceSettings' => [
                'enableIntraCommunityDeliveryLabel' => true,
                'setCustomerShippingCountryAsMemberCountry' => true,
            ],
            'enableTaxFreeB2bOption' => true,
            'expectedOutput' => true,
        ];

        yield 'shall not be displayed cause customer account is no B2B account' => [
            'customerSettings' => [
                'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE, ],
            'invoiceSettings' => [
                'enableIntraCommunityDeliveryLabel' => true,
                'setCustomerShippingCountryAsMemberCountry' => true,
            ],
            'enableTaxFreeB2bOption' => true,
            'expectedOutput' => false,
        ];

        yield 'shall not be displayed cause customer shipping country is not in "member country" list' => [
            'customerSettings' => [
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS, ],
            'invoiceSettings' => [
                'enableIntraCommunityDeliveryLabel' => true,
                'setCustomerShippingCountryAsMemberCountry' => false,
            ],
            'enableTaxFreeB2bOption' => true,
            'expectedOutput' => false,
        ];
    }

    /**
     * @param array<int|string, int> $taxes
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

    /**
     * @param array{accountType: string} $config
     */
    private function updateCustomer(OrderEntity $order, array $config): void
    {
        $this->getContainer()->get('customer.repository')->update([[
            'id' => $order->getOrderCustomer()?->getCustomerId(),
            'accountType' => $config['accountType'],
        ]], Context::createDefaultContext());
    }

    /**
     * @param array{enableIntraCommunityDeliveryLabel: bool, setCustomerShippingCountryAsMemberCountry: bool} $config
     */
    private function updateInvoiceConfig(array $config): void
    {
        $data = [
            'displayAdditionalNoteDelivery' => $config['enableIntraCommunityDeliveryLabel'],
        ];

        $this->upsertBaseConfig($data, InvoiceRenderer::TYPE);
    }

    private function updateCountryMemberState(OrderEntity $order, bool $isEu): void
    {
        $this->getContainer()->get('country.repository')->upsert([[
            'id' => $order->getAddresses()?->get($order->getBillingAddressId())?->getCountry()?->getId(),
            'isEu' => $isEu,
        ]], Context::createDefaultContext());
    }

    private function updateCountrySettings(OrderEntity $order): void
    {
        $this->getContainer()->get('country.repository')->upsert([[
            'id' => $order->getAddresses()?->get($order->getBillingAddressId())?->getCountry()?->getId(),
            'companyTax' => ['amount' => 0, 'enabled' => true, 'currencyId' => Context::createDefaultContext()->getCurrencyId()],
        ]], Context::createDefaultContext());
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
