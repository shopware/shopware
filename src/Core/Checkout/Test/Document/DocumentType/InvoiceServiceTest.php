<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\DocumentType;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehaviorContext;
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
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Checkout\Document\FileGenerator\PdfGenerator;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class InvoiceServiceTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var CheckoutContext
     */
    private $checkoutContext;

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

        $customerId = $this->createCustomer();
        $shippingMethodId = $this->createShippingMethod();

        $this->checkoutContext = $this->getContainer()->get(CheckoutContextFactory::class)->create(
            Uuid::randomHex(),
            Defaults::SALES_CHANNEL,
            [
                CheckoutContextService::CUSTOMER_ID => $customerId,
                CheckoutContextService::SHIPPING_METHOD_ID => $shippingMethodId,
            ]
        );
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

        file_put_contents('/tmp/test2.pdf', $pdfGenerator->generateAsString($processedTemplate));
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
        $cart = $this->getContainer()->get(Enrichment::class)->enrich($cart, $this->checkoutContext);
        $cart = $this->getContainer()->get(Processor::class)->process($cart, $this->checkoutContext, new CartBehaviorContext());

        return $cart;
    }

    private function persistCart(Cart $cart): string
    {
        $events = $this->getContainer()->get(OrderPersister::class)->persist($cart, $this->checkoutContext);
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
                    'countryId' => Defaults::COUNTRY,
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

    private function createShippingMethod(): string
    {
        $shippingMethodId = Uuid::randomHex();
        $repository = $this->getContainer()->get('shipping_method.repository');

        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'DHL Express',
            'bindShippingfree' => false,
            'active' => true,
            'prices' => [
                [
                    'shippingMethodId' => $shippingMethodId,
                    'quantityFrom' => 0,
                    'price' => '10.00',
                    'factor' => 0,
                ],
            ],
        ];

        $repository->create([$data], $this->context);

        return $shippingMethodId;
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
