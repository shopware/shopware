<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\DocumentType;

use Exception;
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
use Shopware\Core\Checkout\Document\DocumentContext;
use Shopware\Core\Checkout\Document\DocumentType\InvoiceService;
use Shopware\Core\Checkout\Document\Generator\PdfGenerator;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $customerId = $this->createCustomer();
        $shippingMethodId = $this->createShippingMethod();

        $this->checkoutContext = $this->getContainer()->get(CheckoutContextFactory::class)->create(
            Uuid::uuid4()->getHex(),
            Defaults::SALES_CHANNEL,
            [
                CheckoutContextService::CUSTOMER_ID => $customerId,
                CheckoutContextService::SHIPPING_METHOD_ID => $shippingMethodId,
            ]
        );
    }

    public function testGenerateFromTemplate()
    {
        $invoiceService = $this->getContainer()->get(InvoiceService::class);
        $pdfGenerator = $this->getContainer()->get(PdfGenerator::class);

        $cart = $this->generateDemoCart(75);
        $orderId = $this->persistCart($cart);
        $order = $this->getOrderById($orderId);

        $documentContext = new DocumentContext();
        $context = Context::createDefaultContext();

        $processedTemplate = $invoiceService->generateFromTemplate(
            $order,
            $documentContext,
            $context
        );

        file_put_contents('/tmp/test.html', $processedTemplate);

        file_put_contents('/tmp/test2.pdf', $pdfGenerator->generateAsString($processedTemplate));
    }

    /**
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws MixedLineItemTypeException
     * @throws Exception
     */
    private function generateDemoCart(int $lineItemCount): Cart
    {
        $cart = new Cart('A', 'a-b-c');
        $deliveryInformation = new DeliveryInformation(
            100,
            0,
            new DeliveryDate(new \DateTime(), new \DateTime()),
            new DeliveryDate(new \DateTime(), new \DateTime())
        );

        $keywords = ['awesome', 'epic', 'high quality'];

        for ($i = 0; $i < $lineItemCount; ++$i) {
            $price = random_int(100, 200000) / 100.0;
            $quantity = random_int(1, 25);
            $taxes = [7, 19, 22];
            $taxRate = $taxes[array_rand($taxes)];
            shuffle($keywords);
//            $name = ucfirst(implode($keywords, ' ') . ' product');
            $name = 'A';
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
            self::fail('Order could not be persisted');
        }

        return $orderIds[0];
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::uuid4()->getHex();
        $addressId = Uuid::uuid4()->getHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutation' => 'Mr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::uuid4()->getHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => Defaults::COUNTRY,
                    'salutation' => 'Mr',
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
        $shippingMethodId = Uuid::uuid4()->getHex();
        $repository = $this->getContainer()->get('shipping_method.repository');

        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'DHL Express',
            'bindShippingfree' => false,
            'active' => true,
            'prices' => [
                [
                    'shippingMethodId' => Defaults::SHIPPING_METHOD,
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
     * @param string $orderId
     *
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
        self::assertNotNull($orderId);

        return $order;
    }
}
