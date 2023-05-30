<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Renderer;

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
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Test\Document\DocumentTrait;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\SalesChannelThemeLoader;
use Shopware\Storefront\Theme\ThemeService;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;

/**
 * @internal
 */
#[Package('customer-order')]
class InvoiceRendererTest extends TestCase
{
    use AppSystemTestBehaviour;
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private EntityRepository $productRepository;

    private InvoiceRenderer $invoiceRenderer;

    private CartService $cartService;

    private static string $deLanguageId;

    private static ?\Closure $callback = null;

    protected function setUp(): void
    {
        $this->initServices();
    }

    protected function tearDown(): void
    {
        if (self::$callback) {
            $this->getContainer()->get('event_dispatcher')->removeListener(DocumentTemplateRendererParameterEvent::class, self::$callback);
        }
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
            $beforeRenderHook($operationInvoice, $this->getContainer());
        }

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

        static::assertInstanceOf(RendererResult::class, $processedTemplate);
        if (!empty($processedTemplate->getSuccess())) {
            static::assertArrayHasKey($orderId, $processedTemplate->getSuccess());

            $rendered = $processedTemplate->getSuccess()[$orderId];

            static::assertInstanceOf(OrderLineItemCollection::class, $lineItems = $order->getLineItems());
            static::assertInstanceOf(OrderLineItemEntity::class, $firstLineItem = $lineItems->first());
            static::assertInstanceOf(OrderLineItemEntity::class, $lastLineItem = $lineItems->last());
            static::assertStringContainsString('<html>', $rendered->getHtml());
            static::assertStringContainsString('</html>', $rendered->getHtml());
            static::assertStringContainsString($firstLineItem->getLabel(), $rendered->getHtml());
            static::assertStringContainsString($lastLineItem->getLabel(), $rendered->getHtml());

            $assertionCallback($rendered, $order, $this->getContainer());
        } else {
            $assertionCallback($order->getId(), $processedTemplate->getErrors());
        }
    }

    public static function invoiceDataProvider(): \Generator
    {
        yield 'render with default language' => [
            [7],
            null,
            function (RenderedDocument $rendered, OrderEntity $order, ContainerInterface $container): void {
                static::assertNotNull($order->getCurrency());
                static::assertStringContainsString(
                    $container->get(CurrencyFormatter::class)->formatCurrencyByLanguage(
                        $order->getAmountTotal(),
                        $order->getCurrency()->getIsoCode(),
                        (Context::createDefaultContext())->getLanguageId(),
                        Context::createDefaultContext(),
                    ),
                    $rendered->getHtml()
                );
            },
        ];

        yield 'render with different language' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $container->get('order.repository')->upsert([[
                    'id' => $operation->getOrderId(),
                    'languageId' => self::$deLanguageId,
                ]], Context::createDefaultContext());

                $criteria = OrderDocumentCriteriaFactory::create([$operation->getOrderId()]);
                /** @var OrderEntity $order */
                $order = $container->get('order.repository')->search($criteria, Context::createDefaultContext())->get($operation->getOrderId());

                $context = clone Context::createDefaultContext();
                $context = $context->assign([
                    'languageIdChain' => array_unique(array_filter([self::$deLanguageId, (Context::createDefaultContext())->getLanguageId()])),
                ]);
                static::assertNotNull($order->getDeliveries());
                $container->get('shipping_method.repository')->upsert([[
                    'id' => $order->getDeliveries()->first()->getShippingMethod()->getId(),
                    'name' => 'DE express',
                ]], $context);
            },
            function (RenderedDocument $rendered, OrderEntity $order, ContainerInterface $container): void {
                static::assertNotNull($order->getCurrency());
                static::assertStringContainsString(
                    preg_replace('/\xc2\xa0/', ' ', $container->get(CurrencyFormatter::class)->formatCurrencyByLanguage(
                        $order->getAmountTotal(),
                        $order->getCurrency()->getIsoCode(),
                        self::$deLanguageId,
                        Context::createDefaultContext(),
                    )) ?? '',
                    preg_replace('/\xc2\xa0/', ' ', $rendered->getHtml()) ?? ''
                );
                static::assertStringContainsString('DE express', preg_replace('/\xc2\xa0/', ' ', $rendered->getHtml()) ?? 'DE express');
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
                static::assertEquals(
                    'Errors happened while rendering',
                    ($errors[$orderId]->getMessage())
                );
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
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);
                /** @var OrderEntity $order */
                $order = $container->get('order.repository')->search($criteria, Context::createDefaultContext())->get($orderId);
                static::assertNotNull($order->getDeliveries());
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
                static::assertStringContainsString('123123123', $rendered);
            },
        ];

        yield 'render with billing address' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                /** @var OrderEntity $order */
                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->get($orderId);

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
            },
        ];

        yield 'render customer VAT-ID with displayCustomerVatId is checked' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                /** @var OrderEntity $order */
                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->get($orderId);

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
                    'companyTax' => ['amount' => 0, 'enabled' => true, 'currencyId' => (Context::createDefaultContext())->getCurrencyId()],
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
            function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertInstanceOf(RenderedDocument::class, $rendered);

                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getOrderCustomer());

                /** @var CustomerEntity $customer */
                $customer = $order->getOrderCustomer()->getCustomer();
                $rendered = $rendered->getHtml();

                static::assertNotNull($customer);
                static::assertNotNull($customer->getVatIds());

                $vatId = $customer->getVatIds()[0];

                static::assertStringContainsString("VAT Reg.No: $vatId", $rendered);
            },
        ];

        yield 'render customer VAT-ID with displayCustomerVatId unchecked' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                /** @var OrderEntity $order */
                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->get($orderId);

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
                    'companyTax' => ['amount' => 0, 'enabled' => true, 'currencyId' => (Context::createDefaultContext())->getCurrencyId()],
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
            function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertInstanceOf(RenderedDocument::class, $rendered);

                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getOrderCustomer());

                /** @var CustomerEntity $customer */
                $customer = $order->getOrderCustomer()->getCustomer();
                $rendered = $rendered->getHtml();

                static::assertNotNull($customer);
                static::assertNotNull($customer->getVatIds());

                static::assertStringNotContainsString('VAT Reg.No:', $rendered);
            },
        ];

        yield 'render with customer VAT-ID is null' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                /** @var OrderEntity $order */
                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->get($orderId);

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
                    'companyTax' => ['amount' => 0, 'enabled' => true, 'currencyId' => (Context::createDefaultContext())->getCurrencyId()],
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
            function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertInstanceOf(RenderedDocument::class, $rendered);

                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getOrderCustomer());

                /** @var CustomerEntity $customer */
                $customer = $order->getOrderCustomer()->getCustomer();
                $rendered = $rendered->getHtml();

                static::assertNotNull($customer);
                static::assertEmpty($customer->getVatIds());

                static::assertStringNotContainsString('VAT Reg.No:', $rendered);
            },
        ];
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

    public function testRenderWithThemeSnippet(): void
    {
        if (!$this->getContainer()->has(ThemeService::class) || !$this->getContainer()->has('theme.repository')) {
            static::markTestSkipped('This test needs storefront to be installed.');
        }

        $this->getContainer()->get(Translator::class)->reset();
        $this->getContainer()->get(SalesChannelThemeLoader::class)->reset();

        $themeService = $this->getContainer()->get(ThemeService::class);
        $themeRepo = $this->getContainer()->get('theme.repository');

        $this->loadAppsFromDir(__DIR__ . '/../fixtures/theme');
        $this->reloadAppSnippets();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'SwagTheme'));
        $themeId = $themeRepo->searchIds($criteria, $this->context)->firstId();

        static::assertNotNull($themeId);

        $cart = $this->generateDemoCart([7]);
        $orderId = $this->persistCart($cart);

        $operationInvoice = new DocumentGenerateOperation($orderId);

        static::assertEquals($operationInvoice->getOrderVersionId(), Defaults::LIVE_VERSION);
        static::assertTrue($this->orderVersionExists($orderId, $operationInvoice->getOrderVersionId()));

        $result = $this->invoiceRenderer->render(
            [$orderId => $operationInvoice],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertNotEquals($operationInvoice->getOrderVersionId(), Defaults::LIVE_VERSION);
        static::assertTrue($this->orderVersionExists($orderId, $operationInvoice->getOrderVersionId()));
        static::assertNotEmpty($success = $result->getSuccess()[$orderId]);
        static::assertIsString($content = $success->getHtml());
        static::assertStringNotContainsString('Swag Theme serviceDateNotice EN', $content);

        $this->getContainer()->get(Translator::class)->reset();
        $this->getContainer()->get(SalesChannelThemeLoader::class)->reset();
        $themeService->assignTheme($themeId, $this->salesChannelContext->getSalesChannelId(), $this->context, true);

        $result = $this->invoiceRenderer->render(
            [$orderId => $operationInvoice],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertNotEmpty($success = $result->getSuccess()[$orderId]);
        static::assertIsString($content = $success->getHtml());
        static::assertStringContainsString('Swag Theme serviceDateNotice EN', $content);
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
        self::$deLanguageId = $this->getDeDeLanguageId();
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
}
