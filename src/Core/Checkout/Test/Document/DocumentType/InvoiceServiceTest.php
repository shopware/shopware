<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\DocumentType;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Enrichment;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentGenerated;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Checkout\Document\FileGenerator\PdfGenerator;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
use Shopware\Core\Content\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class InvoiceServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Connection|object
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $this->connection = $this->getContainer()->get(Connection::class);

        $priceRuleId = Uuid::randomHex();
        $customerId = $this->createCustomer();
        $shippingMethodId = $this->createShippingMethod($priceRuleId);
        $paymentMethodId = $this->createPaymentMethod($priceRuleId);

        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            Defaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
                SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethodId,
                SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
            ]
        );

        $this->salesChannelContext->setRuleIds([$priceRuleId]);
    }

    public function testGenerateFromTemplate()
    {
        $invoiceService = $this->getContainer()->get(InvoiceGenerator::class);
        $pdfGenerator = $this->getContainer()->get(PdfGenerator::class);

        $cart = $this->generateDemoCart(75);
        $orderId = $this->persistCart($cart);
        $order = $this->getOrderById($orderId);

        $documentConfiguration = new DocumentConfiguration();
        $context = Context::createDefaultContext();

        $processedTemplate = $invoiceService->generateFromTemplate(
            $order,
            $documentConfiguration,
            $context
        );

        file_put_contents('/tmp/test.html', $processedTemplate);

        $generatedDocument = new DocumentGenerated();
        $generatedDocument->setHtml($processedTemplate);

        file_put_contents('/tmp/test2.pdf', $pdfGenerator->generate($generatedDocument));
    }

    /**
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws MixedLineItemTypeException
     * @throws \Exception
     */
    private function generateDemoCart(int $lineItemCount): Cart
    {
        $cart = new Cart('A', 'a-b-c');
        $deliveryInformation = new DeliveryInformation(
            100,
            0,
            new DeliveryDate(new \DateTime(), new \DateTime()),
            new DeliveryDate(new \DateTime(), new \DateTime()),
            false
        );

        $keywords = ['awesome', 'epic', 'high quality'];

        for ($i = 0; $i < $lineItemCount; ++$i) {
            $price = random_int(100, 200000) / 100.0;
            $quantity = random_int(1, 25);
            $taxes = [7, 19, 22];
            $taxRate = $taxes[array_rand($taxes)];
            shuffle($keywords);
            $name = ucfirst(implode($keywords, ' ') . ' product');
            $cart->add(
                (new LineItem((string) $i, 'product_' . $i, $quantity))
                    ->setPriceDefinition(new QuantityPriceDefinition($price, new TaxRuleCollection([new TaxRule($taxRate)]), $quantity))
                    ->setLabel($name)
                    ->setPayloadValue('id', '1')
                    ->setStackable(true)
                    ->setDeliveryInformation($deliveryInformation)
            );
        }
        $cart = $this->getContainer()->get(Enrichment::class)->enrich($cart, $this->salesChannelContext, new CartBehavior());
        $cart = $this->getContainer()->get(Processor::class)->process($cart, $this->salesChannelContext, new CartBehavior());

        return $cart;
    }

    private function persistCart(Cart $cart): string
    {
        $events = $this->getContainer()->get(OrderPersister::class)->persist($cart, $this->salesChannelContext);
        $orderIds = $events->getEventByDefinition(OrderDefinition::class)->getIds();

        if (count($orderIds) !== 1) {
            static::fail('Order could not be persisted');
        }

        return $orderIds[0];
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
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
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
            'priceRules' => [
                [
                    'price' => '10.00',
                    'currencyId' => Defaults::CURRENCY,
                    'rule' => [
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                    'calculation' => 1,
                    'quantityStart' => 1,
                ],
            ],
            'deliveryTime' => $this->createDeliveryTimeData(),
            'availabilityRules' => [
                [
                    'id' => $priceRuleId,
                    'name' => 'true',
                    'priority' => 0,
                    'conditions' => [
                        [
                            'type' => 'true',
                        ],
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
            ->addAssociation('transactions');
        $order = $this->getContainer()->get('order.repository')->search($criteria, $this->context)->get($orderId);
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

    private function getValidSalutationId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('salutation.repository');

        $criteria = (new Criteria())->setLimit(1);

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }
}
