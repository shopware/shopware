<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document;

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
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentTypes;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Smalot\PdfParser\Parser;

class DocumentServiceTest extends TestCase
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
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->context = Context::createDefaultContext();

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
        $this->checkoutContext->setRuleIds(array_merge(
            $this->checkoutContext->getPaymentMethod()->getAvailabilityRuleIds(),
            $this->checkoutContext->getShippingMethod()->getAvailabilityRuleIds()
        ));
    }

    public function testCreateDeliveryNotePdf(): void
    {
        $documentService = $this->getContainer()->get(DocumentService::class);

        $cart = $this->generateDemoCart(75);
        $orderId = $this->persistCart($cart);

        $documentStruct = $documentService->create(
            $orderId,
            DocumentTypes::DELIVERY_NOTE,
            FileTypes::PDF,
            new DocumentConfiguration(),
            $this->context
        );

        static::assertTrue(Uuid::isValid($documentStruct->getId()));

        $documentRepository = $this->getContainer()->get('document.repository');
        /** @var DocumentEntity $document */
        $document = $documentRepository->search(new Criteria([$documentStruct->getId()]), $this->context)->get($documentStruct->getId());

        static::assertNotNull($document);
        static::assertSame($orderId, $document->getOrderId());
        static::assertNotSame(Defaults::LIVE_VERSION, $document->getOrderVersionId());
        static::assertSame(DocumentTypes::DELIVERY_NOTE, $document->getDocumentType()->getTechnicalName());
        static::assertSame(FileTypes::PDF, $document->getFileType());
    }

    public function testGetInvoicePdfDocumentById(): void
    {
        $documentService = $this->getContainer()->get(DocumentService::class);

        $cart = $this->generateDemoCart(75);
        $orderId = $this->persistCart($cart);

        $document = $documentService->create(
            $orderId,
            DocumentTypes::INVOICE,
            FileTypes::PDF,
            new DocumentConfiguration(),
            $this->context
        );

        $document = $documentService->getDocumentByIdAndToken($document->getId(), $document->getDeepLinkCode(), $this->context);
        $renderedDocument = $documentService->renderDocument($document, $this->context);

        $parser = new Parser();
        @$parsedDocument = $parser->parseContent($renderedDocument->getFileBlob());

        if ($cart->getLineItems()->count() <= 0) {
            static::fail('No line items found');
        }

        foreach ($cart->getLineItems() as $lineItem) {
            static::assertStringContainsString($lineItem->getLabel(), $parsedDocument->getText());
        }
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
        $cart = $this->getContainer()->get(CartService::class)->recalculate($cart, $this->checkoutContext);
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
            'availabilityRules' => [
                [
                    'name' => 'Cart > 0',
                    'priority' => 100,
                    'conditions' => [
                        [
                            'type' => (new CartAmountRule())->getName(),
                            'value' => [
                                'amount' => 0,
                                'operator' => '>=',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $repository->create([$data], $this->context);

        return $shippingMethodId;
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
