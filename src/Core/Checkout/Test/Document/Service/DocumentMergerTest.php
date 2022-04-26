<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Tcpdf\Fpdi;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\DocumentMerger;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Media\File\FileLoader;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

class DocumentMergerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->context = Context::createDefaultContext();

        $customerId = $this->createCustomer();

        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
            ]
        );
    }

    public function testMergeDocumentsWithoutDoc(): void
    {
        $merger = $this->getContainer()->get(DocumentMerger::class);

        $mergeResult = $merger->mergeDocuments([Uuid::randomHex()], $this->context);

        static::assertNull($mergeResult);
    }

    public function testMergeDocumentsWithOneDoc(): void
    {
        $merger = $this->getContainer()->get(DocumentMerger::class);
        $documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $mergeResult = $merger->mergeDocuments([Uuid::randomHex()], $this->context);

        static::assertNull($mergeResult);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF);

        $invoiceStruct = $documentGenerator->generate('invoice', [$orderId => $operation], $this->context)->first();

        $mergeResult = $merger->mergeDocuments([$invoiceStruct->getId()], $this->context);

        static::assertInstanceOf(RenderedDocument::class, $mergeResult);
        static::assertNotNull($mergeResult->getContent());
        static::assertNotNull($mergeResult->getName(), 'invoice_1000.pdf');
    }

    public function testMergeDocuments(): void
    {
        $expectedBlob = 'expected blob';

        $mockFpdi = $this->getMockBuilder(Fpdi::class)->onlyMethods(['Output'])->getMock();
        $mockFpdi->expects(static::once())->method('OutPut')->willReturn($expectedBlob);

        $documentMerger = new DocumentMerger(
            $this->getContainer()->get('document.repository'),
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get(DocumentGenerator::class),
            $mockFpdi,
        );

        $documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $deliveryOperation = new DocumentGenerateOperation($orderId);
        $invoiceOperation = new DocumentGenerateOperation($orderId);

        $invoiceDoc = $documentGenerator->generate('invoice', [$orderId => $invoiceOperation], $this->context)->first();
        $deliveryDoc = $documentGenerator->generate('delivery_note', [$orderId => $deliveryOperation], $this->context)->first();

        static::assertNotNull($invoiceDoc);
        static::assertNotNull($deliveryDoc);

        $mergeResult = $documentMerger->mergeDocuments([$invoiceDoc->getId(), $deliveryDoc->getId()], $this->context);

        static::assertInstanceOf(RenderedDocument::class, $mergeResult);
        static::assertEquals($mergeResult->getContent(), $expectedBlob);
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

        $keywords = ['awesome', 'epic', 'high quality'];

        $products = [];

        $factory = new ProductLineItemFactory();

        for ($i = 0; $i < $lineItemCount; ++$i) {
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
                'tax' => ['id' => $id, 'taxRate' => 19, 'name' => 'test'],
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
        $cart = $this->getContainer()->get(CartService::class)->recalculate($cart, $this->salesChannelContext);
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
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getAvailablePaymentMethod()->getId(),
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

    private function getValidSalutationId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('salutation.repository');

        $criteria = (new Criteria())->setLimit(1);

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }
}
