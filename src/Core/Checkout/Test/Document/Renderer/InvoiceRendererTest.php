<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\Renderer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Document\Event\DocumentGeneratorCriteriaEvent;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

class InvoiceRendererTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private Connection $connection;

    private CurrencyFormatter $currencyFormatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->currencyFormatter = $this->getContainer()->get(CurrencyFormatter::class);

        $priceRuleId = Uuid::randomHex();
        $customerId = $this->createCustomer();
        $shippingMethodId = $this->createShippingMethod($priceRuleId);
        $paymentMethodId = $this->createPaymentMethod($priceRuleId);

        $this->addCountriesToSalesChannel();

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

    public function testRenderWithDifferentTaxes(): void
    {
        $invoiceRenderer = $this->getContainer()->get(InvoiceRenderer::class);
        $pdfGenerator = $this->getContainer()->get(PdfRenderer::class);

        $possibleTaxes = [7, 19, 22];
        //generates one line item for each tax
        $cart = $this->generateDemoCart($possibleTaxes);
        $orderId = $this->persistCart($cart);
        /** @var OrderEntity $order */
        $order = $this->getOrderById($orderId);

        $operationInvoice = new DocumentGenerateOperation($orderId);

        $caughtEvent = null;

        $this->getContainer()->get('event_dispatcher')
            ->addListener(DocumentGeneratorCriteriaEvent::class, function (DocumentGeneratorCriteriaEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            });

        $processedTemplate = $invoiceRenderer->render(
            [$order->getId() => $operationInvoice],
            $this->context
        );

        static::assertInstanceOf(DocumentGeneratorCriteriaEvent::class, $caughtEvent);
        static::assertEquals($caughtEvent->getDocumentType(), 'invoice');
        static::assertCount(1, $caughtEvent->getOperations());

        static::assertInstanceOf(RenderedDocument::class, $processedTemplate[$orderId]);

        $rendered = $processedTemplate[$orderId];

        static::assertStringContainsString('<html>', $rendered->getHtml());
        static::assertStringContainsString('</html>', $rendered->getHtml());
        static::assertStringContainsString($order->getLineItems()->first()->getLabel(), $rendered->getHtml());
        static::assertStringContainsString($order->getLineItems()->last()->getLabel(), $rendered->getHtml());
        static::assertStringContainsString(
            $this->currencyFormatter->formatCurrencyByLanguage(
                $order->getAmountTotal(),
                $order->getCurrency()->getIsoCode(),
                $this->context->getLanguageId(),
                $this->context
            ),
            $rendered->getHtml()
        );
        foreach ($possibleTaxes as $possibleTax) {
            static::assertStringContainsString(
                sprintf('plus %d%% VAT', $possibleTax),
                $rendered->getHtml()
            );
        }

        $generatorOutput = $pdfGenerator->render($rendered);
        static::assertNotEmpty($generatorOutput);

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        static::assertEquals('application/pdf', $finfo->buffer($generatorOutput));

        $deLanguageId = $this->getDeDeLanguageId();

        $this->getContainer()->get('order.repository')->upsert([[
            'id' => $order->getId(),
            'languageId' => $deLanguageId,
        ]], $this->context);

        $processedTemplate = $invoiceRenderer->render(
            [$orderId => $operationInvoice],
            $this->context
        );

        static::assertInstanceOf(RenderedDocument::class, $processedTemplate[$orderId]);

        $rendered = $processedTemplate[$orderId];

        static::assertStringContainsString(
            preg_replace('/\xc2\xa0/', ' ', $this->currencyFormatter->formatCurrencyByLanguage(
                $order->getAmountTotal(),
                $order->getCurrency()->getIsoCode(),
                $deLanguageId,
                $this->context
            )),
            preg_replace('/\xc2\xa0/', ' ', $rendered->getHtml())
        );
    }

    public function testRenderWithShippingAddress(): void
    {
        $invoiceRenderer = $this->getContainer()->get(InvoiceRenderer::class);

        $possibleTaxes = [7, 19, 22];
        //generates one line item for each tax
        $cart = $this->generateDemoCart($possibleTaxes);
        $orderId = $this->persistCart($cart);
        /** @var OrderEntity $order */
        $order = $this->getOrderById($orderId);
        $country = $order->getDeliveries()->getShippingAddress()->getCountries()->first();
        $country->setCompanyTax(new TaxFreeConfig(true, Defaults::CURRENCY, 0));

        $this->getContainer()->get('country.repository')->update([[
            'id' => $country->getId(),
            'companyTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
        ]], $this->context);
        $companyPhone = '123123123';
        $vatIds = ['VAT-123123'];
        $order->getOrderCustomer()->setVatIds($vatIds);

        $this->getContainer()->get('order_customer.repository')->update([[
            'id' => $order->getOrderCustomer()->getId(),
            'vatIds' => $vatIds,
        ]], $this->context);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, [
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
        ]);

        $processedTemplate = $invoiceRenderer->render(
            [$orderId => $operation],
            $this->context
        );

        $shippingAddress = $order->getDeliveries()->getShippingAddress()->first();

        static::assertInstanceOf(RenderedDocument::class, $processedTemplate[$orderId]);

        $rendered = $processedTemplate[$orderId]->getHtml();

        static::assertStringContainsString('Shipping address', $rendered);
        static::assertStringContainsString($shippingAddress->getStreet(), $rendered);
        static::assertStringContainsString($shippingAddress->getCity(), $rendered);
        static::assertStringContainsString($shippingAddress->getFirstName(), $rendered);
        static::assertStringContainsString($shippingAddress->getLastName(), $rendered);
        static::assertStringContainsString($shippingAddress->getZipcode(), $rendered);
        static::assertStringContainsString('Intra-community delivery (EU)', $rendered);
        static::assertStringContainsString($vatIds[0], $rendered);
        static::assertStringContainsString($companyPhone, $rendered);
    }

    /**
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws MixedLineItemTypeException
     * @throws \Exception
     */
    private function generateDemoCart(array $taxes): Cart
    {
        $cart = new Cart('A', 'a-b-c');

        $keywords = ['awesome', 'epic', 'high quality'];

        $products = [];

        $factory = new ProductLineItemFactory();

        foreach ($taxes as $tax) {
            $id = Uuid::randomHex();

            $price = random_int(100, 200000) / 100.0;

            shuffle($keywords);
            $name = ucfirst(implode(' ', $keywords) . ' product');

            $products[] = [
                'id' => $id,
                'name' => $name,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price, 'linked' => false],
                ],
                'productNumber' => Uuid::randomHex(),
                'manufacturer' => ['id' => $id, 'name' => 'test'],
                'tax' => ['id' => $id, 'taxRate' => $tax, 'name' => 'test'],
                'stock' => 10,
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];

            $cart->add($factory->create($id));
            $this->addTaxDataToSalesChannel($this->salesChannelContext, end($products)['tax']);
        }

        $this->getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());

        $cart = $this->getContainer()->get(Processor::class)->process($cart, $this->salesChannelContext, new CartBehavior());

        return $cart;
    }

    private function persistCart(Cart $cart): string
    {
        $orderId = $this->getContainer()->get(OrderPersister::class)->persist($cart, $this->salesChannelContext);

        return $orderId;
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getDefaultPaymentMethod(),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->getContainer()->get('customer.repository')->upsert([$customer], $this->context);

        return $customerId;
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

    /**
     * @throws InconsistentCriteriaIdsException
     *
     * @return mixed|null
     */
    private function getOrderById(string $orderId)
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('currency')
            ->addAssociation('language.locale')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('orderCustomer.customer');

        $order = $this->getContainer()->get('order.repository')
            ->search($criteria, $this->context)
            ->get($orderId);

        static::assertNotNull($orderId);

        return $order;
    }

    private function getDefaultPaymentMethod(): ?string
    {
        $id = $this->connection->executeQuery(
            'SELECT `id` FROM `payment_method` WHERE `active` = 1 ORDER BY `position` ASC'
        )->fetchColumn();

        if (!$id) {
            return null;
        }

        return Uuid::fromBytesToHex($id);
    }
}
