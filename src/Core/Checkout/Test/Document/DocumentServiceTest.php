<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\StornoGenerator;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Content\Media\MediaType\BinaryType;
use Shopware\Core\Content\Media\Pathname\UrlGenerator;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\RuleTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Smalot\PdfParser\Parser;

class DocumentServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use RuleTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

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

        $paymentMethod = $this->getAvailablePaymentMethod();

        $customerId = $this->createCustomer($paymentMethod->getId());
        $shippingMethod = $this->getAvailableShippingMethod();

        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            Defaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
                SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethod->getId(),
                SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethod->getId(),
            ]
        );

        $this->salesChannelContext->setRuleIds([
            $shippingMethod->getAvailabilityRuleId(),
            $paymentMethod->getAvailabilityRuleId(),
        ]);
    }

    public function testCreateDeliveryNotePdf(): void
    {
        $documentService = $this->getContainer()->get(DocumentService::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $documentStruct = $documentService->create(
            $orderId,
            DeliveryNoteGenerator::DELIVERY_NOTE,
            FileTypes::PDF,
            new DocumentConfiguration(),
            $this->context
        );

        static::assertTrue(Uuid::isValid($documentStruct->getId()));

        $documentRepository = $this->getContainer()->get('document.repository');

        $criteria = new Criteria([$documentStruct->getId()]);
        $criteria->addAssociation('documentType');

        $document = $documentRepository
            ->search($criteria, $this->context)
            ->get($documentStruct->getId());

        static::assertNotNull($document);
        static::assertSame($orderId, $document->getOrderId());
        static::assertNotSame(Defaults::LIVE_VERSION, $document->getOrderVersionId());
        static::assertSame(DeliveryNoteGenerator::DELIVERY_NOTE, $document->getDocumentType()->getTechnicalName());
        static::assertSame(FileTypes::PDF, $document->getFileType());
    }

    public function testCreateStornoBillReferencingInvoice(): void
    {
        $documentService = $this->getContainer()->get(DocumentService::class);

        // create an invoice
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $invoiceStruct = $documentService->create(
            $orderId,
            InvoiceGenerator::INVOICE,
            FileTypes::PDF,
            new DocumentConfiguration(),
            $this->context
        );
        static::assertTrue(Uuid::isValid($invoiceStruct->getId()));

        $documentRepository = $this->getContainer()->get('document.repository');
        /** @var DocumentEntity $invoice */
        $invoice = $documentRepository->search(new Criteria([$invoiceStruct->getId()]), $this->context)->get($invoiceStruct->getId());

        //create a storno bill which references the invoice
        $stornoStruct = $documentService->create(
            $orderId,
            StornoGenerator::STORNO,
            FileTypes::PDF,
            new DocumentConfiguration(),
            $this->context,
            $invoice->getId()
        );
        static::assertTrue(Uuid::isValid($stornoStruct->getId()));

        /** @var DocumentEntity $storno */
        $storno = $documentRepository->search(new Criteria([$stornoStruct->getId()]), $this->context)->get($stornoStruct->getId());
        static::assertSame($storno->getOrderVersionId(), $invoice->getOrderVersionId());
    }

    public function testCreateFileIsWrittenInFs(): void
    {
        /** @var FilesystemInterface $fileSystem */
        $fileSystem = $this->getContainer()->get('shopware.filesystem.private');
        $document = $this->createDocumentWithFile();

        /** @var UrlGenerator $urlGenerator */
        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);

        $filePath = $urlGenerator->getRelativeMediaUrl($document->getDocumentMediaFile());

        static::assertTrue($fileSystem->has($filePath));
        $fileSystem->delete($filePath);
        static::assertFalse($fileSystem->has($filePath));
    }

    public function testGetStaticDocumentFile(): void
    {
        /** @var DocumentService $documentService */
        $documentService = $this->getContainer()->get(DocumentService::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        /** @var FilesystemInterface $fileSystem */
        $fileSystem = $this->getContainer()->get('shopware.filesystem.private');

        /** @var UrlGenerator $urlGenerator */
        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);

        $orderRepository = $this->getContainer()->get('order.repository');

        $documentRepository = $this->getContainer()->get('document.repository');

        $documentTypeRepository = $this->getContainer()->get('document_type.repository');

        $orderVersionId = $orderRepository->createVersion($orderId, $this->context, DocumentService::VERSION_NAME);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', DeliveryNoteGenerator::DELIVERY_NOTE));

        /** @var DocumentTypeEntity $documentType */
        $documentType = $documentTypeRepository->search($criteria, $this->context)->first();

        $documentId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $documentRepository->create(
            [
                [
                    'id' => $documentId,
                    'documentTypeId' => $documentType->getId(),
                    'fileType' => FileTypes::PDF,
                    'orderId' => $orderId,
                    'orderVersionId' => $orderVersionId,
                    'config' => ['documentNumber' => '1001'],
                    'deepLinkCode' => 'dfr',
                    'static' => true,
                    'documentMediaFile' => [
                        'id' => $mediaId,
                        'mimeType' => 'plain/txt',
                        'fileExtension' => 'txt',
                        'fileName' => 'textFileWithExtension',
                        'fileSize' => 1024,
                        'private' => true,
                        'mediaType' => new BinaryType(),
                        'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                    ],
                ],
            ],
            $this->context
        );

        $documentRepository = $this->getContainer()->get('document.repository');
        $criteria = new Criteria([$documentId]);
        $criteria->addAssociation('documentMediaFile');
        /** @var DocumentEntity $document */
        $document = $documentRepository->search($criteria, $this->context)->get($documentId);

        $filePath = $urlGenerator->getRelativeMediaUrl($document->getDocumentMediaFile());

        $fileSystem->put($filePath, 'test123');

        static::assertTrue($fileSystem->has($filePath));

        $generatedDocument = $documentService->getDocument($document, $this->context);

        static::assertEquals('test123', $generatedDocument->getFileBlob());
    }

    public function testGetInvoicePdfDocumentById(): void
    {
        /** @var DocumentService $documentService */
        $documentService = $this->getContainer()->get(DocumentService::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $document = $documentService->create(
            $orderId,
            InvoiceGenerator::INVOICE,
            FileTypes::PDF,
            new DocumentConfiguration(),
            $this->context
        );

        $documentId = $document->getId();

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('id', $document->getId()),
            new EqualsFilter('deepLinkCode', $document->getDeepLinkCode()),
        ]));
        $criteria->addAssociation('documentType');

        /** @var EntityRepositoryInterface $documentRepository */
        $documentRepository = $this->getContainer()->get('document.repository');

        $document = $documentRepository->search($criteria, $this->context)->first();

        if (!$document) {
            throw new InvalidDocumentException($documentId);
        }

        $renderedDocument = $documentService->getDocument($document, $this->context);

        $parser = new Parser();
        $parsedDocument = $parser->parseContent($renderedDocument->getFileBlob());

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
                    ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
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

    private function createCustomer(string $paymentMethodId): string
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
            'defaultPaymentMethodId' => $paymentMethodId,
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

    private function getValidSalutationId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('salutation.repository');

        $criteria = (new Criteria())->setLimit(1);

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    private function createDocumentWithFile(): DocumentEntity
    {
        /** @var DocumentService $documentService */
        $documentService = $this->getContainer()->get(DocumentService::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $documentStruct = $documentService->create(
            $orderId,
            DeliveryNoteGenerator::DELIVERY_NOTE,
            FileTypes::PDF,
            new DocumentConfiguration(),
            $this->context
        );

        static::assertTrue(Uuid::isValid($documentStruct->getId()));

        $documentRepository = $this->getContainer()->get('document.repository');
        $criteria = new Criteria([$documentStruct->getId()]);
        $criteria->addAssociation('documentMediaFile');
        $criteria->addAssociation('documentType');

        /** @var DocumentEntity $document */
        $document = $documentRepository->search($criteria, $this->context)->get($documentStruct->getId());

        $documentService->getDocument($document, $this->context);

        /** @var DocumentEntity $document */
        $document = $documentRepository->search($criteria, $this->context)->get($documentStruct->getId());

        return $document;
    }
}
