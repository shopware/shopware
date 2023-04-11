<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\Renderer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\Event\CreditNoteOrdersEvent;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Checkout\Test\Document\DocumentTrait;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('customer-order')]
class CreditNoteRendererTest extends TestCase
{
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private EntityRepository $productRepository;

    private CreditNoteRenderer $creditNoteRenderer;

    private CartService $cartService;

    private DocumentGenerator $documentGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();
        $shippingMethodId = $this->createShippingMethod($priceRuleId);
        $paymentMethodId = $this->createPaymentMethod($priceRuleId);

        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
                SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethodId,
                SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
            ]
        );

        $this->salesChannelContext->setRuleIds([$priceRuleId]);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->creditNoteRenderer = $this->getContainer()->get(CreditNoteRenderer::class);
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);
    }

    /**
     * @dataProvider creditNoteRendererDataProvider
     *
     * @param array<int, int> $possibleTaxes
     * @param array<int, int> $creditPrices
     * @param array<string, int> $additionalConfig
     */
    public function testRender(
        array $possibleTaxes,
        array $creditPrices,
        ?\Closure $successCallback = null,
        ?\Closure $errorCallback = null,
        array $additionalConfig = []
    ): void {
        $cart = $this->generateDemoCart($possibleTaxes);
        $cart = $this->generateCreditItems($cart, $creditPrices);

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
            ->addListener(CreditNoteOrdersEvent::class, function (CreditNoteOrdersEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            });

        $processedTemplate = $this->creditNoteRenderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertInstanceOf(CreditNoteOrdersEvent::class, $caughtEvent);
        static::assertCount(1, $caughtEvent->getOperations());
        static::assertSame($operation, $caughtEvent->getOperations()[$orderId] ?? null);
        static::assertCount(1, $caughtEvent->getOrders());
        $order = $caughtEvent->getOrders()->get($orderId);
        static::assertNotNull($order);
        static::assertInstanceOf(RendererResult::class, $processedTemplate);

        if ($errorCallback) {
            $errorCallback($orderId, $processedTemplate->getErrors());
        } else {
            static::assertNotEmpty($processedTemplate->getSuccess());
            static::assertArrayHasKey($orderId, $processedTemplate->getSuccess());
            $rendered = $processedTemplate->getSuccess()[$orderId];
            static::assertInstanceOf(RenderedDocument::class, $rendered);
            static::assertStringContainsString('<html>', $rendered->getHtml());
            static::assertStringContainsString('</html>', $rendered->getHtml());

            if ($successCallback) {
                $successCallback($rendered);
            }

            static::assertEmpty($processedTemplate->getErrors());
        }
    }

    public function testRenderWithoutInvoice(): void
    {
        $cart = $this->generateDemoCart([7, 13]);
        $cart = $this->generateCreditItems($cart, [100, 200]);
        $orderId = $this->cartService->order($cart, $this->salesChannelContext, new RequestDataBag());

        $operation = new DocumentGenerateOperation($orderId);

        $processedTemplate = $this->creditNoteRenderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertEmpty($processedTemplate->getSuccess());
        static::assertNotEmpty($errors = $processedTemplate->getErrors());
        static::assertArrayHasKey($orderId, $errors);
        static::assertInstanceOf(DocumentGenerationException::class, $errors[$orderId]);
        static::assertEquals(
            "Unable to generate document. Can not generate credit note document because no invoice document exists. OrderId: $orderId",
            ($errors[$orderId]->getMessage())
        );
    }

    public static function creditNoteRendererDataProvider(): \Generator
    {
        yield 'render credit_note successfully' => [
            [7, 19, 22],
            [-100, -200, -300],
            function (RenderedDocument $rendered): void {
                foreach ([-100, -200, -300] as $price) {
                    static::assertStringContainsString('credit' . $price, $rendered->getHtml());
                }

                foreach ([7, 19, 22] as $possibleTax) {
                    static::assertStringContainsString(
                        sprintf('plus %d%% VAT', $possibleTax),
                        $rendered->getHtml()
                    );
                }

                static::assertStringContainsString(
                    sprintf('€%s', number_format((float) -array_sum([-100, -200, -300]), 2)),
                    $rendered->getHtml()
                );
            },
            null,
        ];

        yield 'render credit_note without credit items' => [
            [7, 19, 22],
            [],
            null,
            function (string $orderId, array $errors): void {
                static::assertNotEmpty($errors);
                static::assertArrayHasKey($orderId, $errors);
                static::assertEquals(
                    "Unable to generate document. Can not generate credit note document because no credit line items exists. OrderId: $orderId",
                    ($errors[$orderId]->getMessage())
                );
            },
        ];

        yield 'render credit_note with document number' => [
            [7, 19, 22],
            [-100, -200, -300],
            function (RenderedDocument $rendered): void {
                static::assertEquals('CREDIT_NOTE_9999', $rendered->getNumber());
                static::assertEquals('credit_note_CREDIT_NOTE_9999', $rendered->getName());
            },
            null,
            [
                'documentNumber' => 'CREDIT_NOTE_9999',
            ],
        ];

        yield 'render credit_note with invoice number' => [
            [7, 19, 22],
            [-100, -200, -300],
            function (RenderedDocument $rendered): void {
                static::assertEquals('1000', $rendered->getNumber());
                static::assertEquals('credit_note_1000', $rendered->getName());
                $config = $rendered->getConfig();
                static::assertArrayHasKey('custom', $config);
                static::assertArrayHasKey('invoiceNumber', $config['custom']);
            },
            null,
            [
                'custom' => [
                    'invoiceNumber' => 'INVOICE_9999',
                ],
            ],
        ];

        yield 'render credit_note without invoice number' => [
            [7, 19, 22],
            [-100, -200, -300],
            function (RenderedDocument $rendered): void {
                static::assertEquals('1000', $rendered->getNumber());
                static::assertEquals('credit_note_1000', $rendered->getName());
                $config = $rendered->getConfig();
                static::assertArrayHasKey('custom', $config);
                static::assertNotEmpty($config['custom']['invoiceNumber']);
            },
        ];

        yield 'render with single page' => [
            [7, 19],
            [-100, -200],
            function (RenderedDocument $rendered): void {
                static::assertInstanceOf(RenderedDocument::class, $rendered);

                $rendered = $rendered->getHtml();

                static::assertStringContainsString('Credit note 1000 for Invoice no. 1001', $rendered);
            },
            null,
            [
                'itemsPerPage' => 2,
            ],
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

        $operationCreditNote = new DocumentGenerateOperation($orderId);

        static::assertEquals($operationCreditNote->getOrderVersionId(), Defaults::LIVE_VERSION);
        static::assertTrue($this->orderVersionExists($orderId, $operationCreditNote->getOrderVersionId()));

        $this->creditNoteRenderer->render(
            [$orderId => $operationCreditNote],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertEquals($operationCreditNote->getOrderVersionId(), Defaults::LIVE_VERSION);
        static::assertTrue($this->orderVersionExists($orderId, $operationCreditNote->getOrderVersionId()));
    }

    /**
     * @dataProvider creditNoteRendererCustomerGroupDataProvider
     *
     * @param array<int, int> $possibleTaxes
     * @param array<int, int> $creditPrices
     */
    public function testGenerateCreditNoteWithCustomerGroup(
        bool $groupNet,
        array $possibleTaxes,
        array $creditPrices,
        \Closure $successCallback
    ): void {
        $this->setSalesChannelContext($groupNet);
        static::assertNotNull($this->salesChannelContext->getCustomer());

        $this->getContainer()->get('customer.repository')->update([
            [
                'id' => $this->salesChannelContext->getCustomer()->getId(),
                'groupId' => $groupNet ? $this->createNetCustomerGroup() : $this->createGrossCustomerGroup(),
            ],
        ], $this->salesChannelContext->getContext());

        $cart = $this->generateDemoCart($possibleTaxes);
        $cart = $this->generateCreditItems($cart, $creditPrices);

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

        $operation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::PDF,
            $config,
            $invoiceId
        );

        $caughtEvent = null;

        $this->getContainer()->get('event_dispatcher')
            ->addListener(CreditNoteOrdersEvent::class, function (CreditNoteOrdersEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            });

        $this->creditNoteRenderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertInstanceOf(CreditNoteOrdersEvent::class, $caughtEvent);

        /** @var OrderEntity $order */
        $order = $caughtEvent->getOrders()->first();

        $successCallback($order);
    }

    public static function creditNoteRendererCustomerGroupDataProvider(): \Generator
    {
        yield 'render credit_note with customer group gross' => [
            false,
            [7],
            [-100],
            function (OrderEntity $order): void {
                static::assertNotNull($lineItems = $order->getLineItems());
                $taxAmount = $lineItems->getPrices()->sum()->getCalculatedTaxes()->getAmount();

                static::assertEquals($order->getPrice()->getTotalPrice(), 100);
                static::assertEquals($order->getAmountNet(), -(-100 - $taxAmount));
            },
        ];

        yield 'render credit_note with customer group net' => [
            true,
            [7],
            [-100],
            function (OrderEntity $order): void {
                static::assertEquals($order->getPrice()->getTotalPrice(), \abs(7) + \abs(-100));
                static::assertEquals($order->getAmountNet(), \abs(-100));
            },
        ];
    }

    /**
     * @param array<int, int> $taxes
     */
    private function generateDemoCart(array $taxes): Cart
    {
        $cart = $this->cartService->createNew('a-b-c');

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

        $cart->setRuleIds($this->salesChannelContext->getRuleIds());

        return $this->cartService->add($cart, $lineItems, $this->salesChannelContext);
    }

    /**
     * @param array<int, int> $creditPrices
     */
    private function generateCreditItems(Cart $cart, array $creditPrices): Cart
    {
        $lineItems = [];

        foreach ($creditPrices as $price) {
            $creditId = Uuid::randomHex();
            $creditLineItem = (new LineItem($creditId, LineItem::CREDIT_LINE_ITEM_TYPE, $creditId, 1))
                ->setLabel('credit' . $price)
                ->setPriceDefinition(new AbsolutePriceDefinition($price));

            $lineItems[] = $creditLineItem;
        }

        $cart->setRuleIds($this->salesChannelContext->getRuleIds());

        return $this->cartService->add($cart, $lineItems, $this->salesChannelContext);
    }

    private function createShippingMethod(string $priceRuleId): string
    {
        $shippingMethodId = Uuid::randomHex();
        $repository = $this->getContainer()->get('shipping_method.repository');

        $ruleRegistry = $this->getContainer()->get(RuleConditionRegistry::class);
        $prop = ReflectionHelper::getProperty(RuleConditionRegistry::class, 'rules');
        $prop->setValue($ruleRegistry, array_merge($prop->getValue($ruleRegistry), ['true' => new TrueRule()]));

        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'test shipping method',
            'bindShippingfree' => false,
            'active' => true,
            'prices' => [
                [
                    'name' => 'Std',
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 10.00,
                            'gross' => 10.00,
                            'linked' => false,
                        ],
                    ],
                    'currencyId' => Defaults::CURRENCY,
                    'calculation' => 1,
                    'quantityStart' => 1,
                ],
            ],
            'deliveryTime' => $this->createDeliveryTimeData(),
            'availabilityRule' => [
                'id' => $priceRuleId,
                'name' => 'true',
                'priority' => 1,
                'conditions' => [
                    [
                        'type' => (new TrueRule())->getName(),
                    ],
                ],
            ],
        ];

        $repository->create([$data], $this->context);

        return $shippingMethodId;
    }

    /**
     * @return array<string, string|int>
     */
    private function createDeliveryTimeData(): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'min' => 1,
            'max' => 90,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
        ];
    }

    private function createPaymentMethod(string $ruleId): string
    {
        $paymentMethodId = Uuid::randomHex();
        $repository = $this->getContainer()->get('payment_method.repository');

        $ruleRegistry = $this->getContainer()->get(RuleConditionRegistry::class);
        $prop = ReflectionHelper::getProperty(RuleConditionRegistry::class, 'rules');
        $prop->setValue($ruleRegistry, array_merge($prop->getValue($ruleRegistry), ['true' => new TrueRule()]));

        $data = [
            'id' => $paymentMethodId,
            'handlerIdentifier' => SyncTestPaymentHandler::class,
            'name' => 'Payment',
            'active' => true,
            'position' => 0,
            'availabilityRules' => [
                [
                    'id' => $ruleId,
                    'name' => 'true',
                    'priority' => 0,
                    'conditions' => [
                        [
                            'type' => 'true',
                        ],
                    ],
                ],
            ],
            'salesChannels' => [
                [
                    'id' => TestDefaults::SALES_CHANNEL,
                ],
            ],
        ];

        $repository->create([$data], $this->context);

        return $paymentMethodId;
    }

    private function setSalesChannelContext(bool $customerGroupNet = false): void
    {
        $priceRuleId = Uuid::randomHex();
        $options = [];
        if ($customerGroupNet) {
            $options = [
                'groupId' => $this->createNetCustomerGroup(),
            ];
        }

        $customerId = $this->createCustomer($options);
        $shippingMethodId = $this->createShippingMethod($priceRuleId);
        $paymentMethodId = $this->createPaymentMethod($priceRuleId);

        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
                SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethodId,
                SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
            ]
        );

        $this->salesChannelContext->setRuleIds([$priceRuleId]);
    }

    private function createNetCustomerGroup(): string
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'displayGross' => false,
            'translations' => [
                'en-GB' => [
                    'name' => 'Net price customer group',
                ],
                'de-DE' => [
                    'name' => 'Nettopreis-Kundengruppe',
                ],
            ],
        ];

        $this->getContainer()->get('customer_group.repository')->create([$data], Context::createDefaultContext());

        return $id;
    }

    private function createGrossCustomerGroup(): string
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'displayGross' => true,
            'translations' => [
                'en-GB' => [
                    'name' => 'Standard customer group',
                ],
                'de-DE' => [
                    'name' => 'Standard-Kundengruppe',
                ],
            ],
        ];

        $this->getContainer()->get('customer_group.repository')->create([$data], Context::createDefaultContext());

        return $id;
    }
}
