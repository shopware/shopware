<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\DocumentType;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentGenerator\CreditNoteGenerator;
use Shopware\Core\Checkout\Document\FileGenerator\PdfGenerator;
use Shopware\Core\Checkout\Document\GeneratedDocument;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class CreditNoteGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;

    public const CUSTOMER_GROUP_GROSS = false;

    public const CUSTOMER_GROUP_NET = true;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private Connection $connection;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        parent::setUp();

        $this->context = Context::createDefaultContext();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testGenerateCreditNotWithCustomerGroupNet(): void
    {
        $this->setSalesChannelContext(self::CUSTOMER_GROUP_NET);

        static::assertNotNull($this->salesChannelContext->getCustomer());
        $this->getContainer()->get('customer.repository')->update([
            [
                'id' => $this->salesChannelContext->getCustomer()->getId(),
                'groupId' => $this->createNetCustomerGroup(),
            ],
        ], $this->salesChannelContext->getContext());

        $creditNoteService = $this->getContainer()->get(CreditNoteGenerator::class);

        $possibleTax = 7;
        $cart = $this->generateDemoCart([$possibleTax]);
        $creditPrice = -100;

        $cart = $this->generateCreditItems($cart, [$creditPrice]);

        $orderId = $this->persistCart($cart);
        /** @var OrderEntity $order */
        $order = $this->getOrderById($orderId);

        $documentConfiguration = DocumentConfigurationFactory::mergeConfiguration(
            new DocumentConfiguration(),
            [
                'displayLineItems' => true,
                'itemsPerPage' => 10,
                'displayFooter' => true,
                'displayHeader' => true,
            ]
        );
        $context = Context::createDefaultContext();

        $creditNoteService->generate(
            $order,
            $documentConfiguration,
            $context
        );

        static::assertEquals($order->getPrice()->getTotalPrice(), \abs($possibleTax) + \abs($creditPrice));
        static::assertEquals($order->getAmountNet(), \abs($creditPrice));
    }

    public function testGenerateCreditNotWithCustomerGroupGross(): void
    {
        $this->setSalesChannelContext(self::CUSTOMER_GROUP_GROSS);

        static::assertNotNull($this->salesChannelContext->getCustomer());
        $this->getContainer()->get('customer.repository')->update([
            [
                'id' => $this->salesChannelContext->getCustomer()->getId(),
                'groupId' => $this->createGrossCustomerGroup(),
            ],
        ], $this->salesChannelContext->getContext());

        $creditNoteService = $this->getContainer()->get(CreditNoteGenerator::class);

        $possibleTax = 7;
        $cart = $this->generateDemoCart([$possibleTax]);
        $creditPrice = -100;

        $cart = $this->generateCreditItems($cart, [$creditPrice]);

        $orderId = $this->persistCart($cart);
        /** @var OrderEntity $order */
        $order = $this->getOrderById($orderId);

        $documentConfiguration = DocumentConfigurationFactory::mergeConfiguration(
            new DocumentConfiguration(),
            [
                'displayLineItems' => true,
                'itemsPerPage' => 10,
                'displayFooter' => true,
                'displayHeader' => true,
            ]
        );
        $context = Context::createDefaultContext();

        $creditNoteService->generate(
            $order,
            $documentConfiguration,
            $context
        );

        static::assertNotNull($lineItems = $order->getLineItems());
        $taxAmount = $lineItems->getPrices()->sum()->getCalculatedTaxes()->getAmount();

        static::assertEquals($order->getPrice()->getTotalPrice(), -$creditPrice);
        static::assertEquals($order->getAmountNet(), -($creditPrice - $taxAmount));
    }

    public function testGenerateWithDifferentTaxes(): void
    {
        $this->setSalesChannelContext();

        $creditNoteService = $this->getContainer()->get(CreditNoteGenerator::class);
        $pdfGenerator = $this->getContainer()->get(PdfGenerator::class);

        $possibleTaxes = [7, 19, 22];
        //generates one line item for each tax
        $cart = $this->generateDemoCart($possibleTaxes);
        $creditPrices = [-100, -200, -300];
        //generates credit items for each price
        $cart = $this->generateCreditItems($cart, $creditPrices);

        $orderId = $this->persistCart($cart);
        /** @var OrderEntity $order */
        $order = $this->getOrderById($orderId);

        $documentConfiguration = DocumentConfigurationFactory::mergeConfiguration(
            new DocumentConfiguration(),
            [
                'displayLineItems' => true,
                'itemsPerPage' => 10,
                'displayFooter' => true,
                'displayHeader' => true,
            ]
        );
        $context = Context::createDefaultContext();

        $processedTemplate = $creditNoteService->generate(
            $order,
            $documentConfiguration,
            $context
        );

        $lineItems = $order->getLineItems();

        static::assertNotNull($lineItems);
        static::assertCount(\count($creditPrices), $lineItems);

        foreach ($lineItems as $lineItem) {
            static::assertEquals(LineItem::CREDIT_LINE_ITEM_TYPE, $lineItem->getType());
        }

        static::assertStringContainsString('<html>', $processedTemplate);
        static::assertStringContainsString('</html>', $processedTemplate);

        foreach ($creditPrices as $price) {
            static::assertStringContainsString('credit' . $price, $processedTemplate);
        }

        foreach ($possibleTaxes as $possibleTax) {
            static::assertStringContainsString(
                sprintf('plus %d%% VAT', $possibleTax),
                $processedTemplate
            );
        }

        static::assertStringContainsString(
            sprintf('€%s', number_format((float) -array_sum($creditPrices), 2)),
            $processedTemplate
        );

        $generatedDocument = new GeneratedDocument();
        $generatedDocument->setHtml($processedTemplate);

        $generatorOutput = $pdfGenerator->generate($generatedDocument);
        static::assertNotEmpty($generatorOutput);

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        static::assertEquals('application/pdf', $finfo->buffer($generatorOutput));
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

    /**
     * @param array<float> $taxes
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

    /**
     * @param array<float> $creditPrices
     */
    private function generateCreditItems(Cart $cart, array $creditPrices): Cart
    {
        foreach ($creditPrices as $price) {
            $creditId = Uuid::randomHex();
            $creditLineItem = (new LineItem($creditId, LineItem::CREDIT_LINE_ITEM_TYPE, $creditId, 1))
                ->setLabel('credit' . $price)
                ->setPriceDefinition(new AbsolutePriceDefinition($price));
            $cart->addLineItems(new LineItemCollection([$creditLineItem]));
        }
        $cart = $this->getContainer()->get(Processor::class)->process($cart, $this->salesChannelContext, new CartBehavior());

        return $cart;
    }

    private function persistCart(Cart $cart): string
    {
        $orderId = $this->getContainer()->get(OrderPersister::class)->persist($cart, $this->salesChannelContext);

        return $orderId;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createCustomer(array $options = []): string
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

        $customer = array_merge($customer, $options);

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

    /**
     * @return array<string, int|string>
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

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getOrderById(string $orderId): OrderEntity
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('addresses.country')
            ->addAssociation('currency')
            ->addAssociation('language.locale')
            ->addAssociation('transactions');

        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')
            ->search($criteria, $this->context)
            ->get($orderId);

        return $order;
    }

    private function getDefaultPaymentMethod(): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT `id` FROM `payment_method` WHERE `active` = 1 ORDER BY `position` ASC'
        );

        if (!$id) {
            return null;
        }

        return Uuid::fromBytesToHex($id);
    }
}
