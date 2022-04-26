<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\Service;

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
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\Exception\DocumentNumberAlreadyExistsException;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Media\File\FileLoader;
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
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

class DocumentGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private Connection $connection;

    private DocumentGenerator $documentGenerator;

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

        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);
    }

    public function testCreateDeliveryNotePdf(): void
    {
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId);

        $documentStruct = $this->documentGenerator->generate('delivery_note', [$orderId => $operation], $this->context)->first();

        static::assertTrue(Uuid::isValid($documentStruct->getId()));

        $documentRepository = $this->getContainer()->get('document.repository');

        $criteria = new Criteria([$documentStruct->getId()]);
        $criteria->addAssociation('documentType');

        $document = $documentRepository
            ->search($criteria, $this->context)
            ->get($documentStruct->getId());

        static::assertNotNull($document);
        static::assertSame($orderId, $document->getOrderId());

        static::assertSame(Defaults::LIVE_VERSION, $document->getOrderVersionId());
        static::assertSame('delivery_note', $document->getDocumentType()->getTechnicalName());
        static::assertSame(FileTypes::PDF, $document->getFileType());
    }

    public function testCreateStornoBillReferencingInvoice(): void
    {
        // create an invoice
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId);

        $invoiceStruct = $this->documentGenerator->generate('invoice', [$orderId => $operation], $this->context)->first();

        static::assertTrue(Uuid::isValid($invoiceStruct->getId()));

        $documentRepository = $this->getContainer()->get('document.repository');
        /** @var DocumentEntity $invoice */
        $invoice = $documentRepository->search(new Criteria([$invoiceStruct->getId()]), $this->context)->get($invoiceStruct->getId());

        //create a storno bill which references the invoice
        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, [], $invoice->getId());

        $stornoStruct = $this->documentGenerator->generate('storno', [$orderId => $operation], $this->context)->first();

        static::assertTrue(Uuid::isValid($stornoStruct->getId()));

        /** @var DocumentEntity $storno */
        $storno = $documentRepository->search(new Criteria([$stornoStruct->getId()]), $this->context)->get($stornoStruct->getId());

        static::assertNotEmpty($storno);
        static::assertEquals($invoice->getId(), $storno->getReferencedDocumentId());
        static::assertSame($storno->getOrderVersionId(), $invoice->getOrderVersionId());
    }

    /**
     * @group slow
     */
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
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        /** @var FilesystemInterface $fileSystem */
        $fileSystem = $this->getContainer()->get('shopware.filesystem.private');

        /** @var UrlGenerator $urlGenerator */
        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);

        $documentRepository = $this->getContainer()->get('document.repository');

        $documentTypeRepository = $this->getContainer()->get('document_type.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'delivery_note'));

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
                    'orderVersionId' => Defaults::LIVE_VERSION,
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

        $criteria = new Criteria([$documentId]);
        $criteria->addAssociation('documentMediaFile');
        /** @var DocumentEntity $document */
        $document = $documentRepository->search($criteria, $this->context)->get($documentId);

        $filePath = $urlGenerator->getRelativeMediaUrl($document->getDocumentMediaFile());

        $fileSystem->put($filePath, 'test123');

        static::assertTrue($fileSystem->has($filePath));

        $generatedDocument = $this->documentGenerator->readDocument($document->getId(), $this->context);

        static::assertEquals('test123', $generatedDocument->getContent());
    }

    public function testGetStaticDocumentFileWithIncorrectDeepLinkCode(): void
    {
        $documentId = Uuid::randomHex();

        static::expectException(InvalidDocumentException::class);
        static::expectExceptionMessage(\sprintf('The document with id "%s" is invalid or could not be found.', $documentId));

        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        /** @var FilesystemInterface $fileSystem */
        $fileSystem = $this->getContainer()->get('shopware.filesystem.private');

        /** @var UrlGenerator $urlGenerator */
        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);

        $documentRepository = $this->getContainer()->get('document.repository');

        $documentTypeRepository = $this->getContainer()->get('document_type.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'delivery_note'));

        /** @var DocumentTypeEntity $documentType */
        $documentType = $documentTypeRepository->search($criteria, $this->context)->first();

        $mediaId = Uuid::randomHex();
        $documentRepository->create(
            [
                [
                    'id' => $documentId,
                    'documentTypeId' => $documentType->getId(),
                    'fileType' => FileTypes::PDF,
                    'orderId' => $orderId,
                    'orderVersionId' => Defaults::LIVE_VERSION,
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

        $criteria = new Criteria([$documentId]);
        $criteria->addAssociation('documentMediaFile');
        /** @var DocumentEntity $document */
        $document = $documentRepository->search($criteria, $this->context)->get($documentId);

        $filePath = $urlGenerator->getRelativeMediaUrl($document->getDocumentMediaFile());

        $fileSystem->put($filePath, 'test123');

        static::assertTrue($fileSystem->has($filePath));

        $this->documentGenerator->readDocument($document->getId(), $this->context, 'wrong code');
    }

    public function testConfigurationWithSalesChannelOverride(): void
    {
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $base = $this->getBaseConfig('invoice');
        $globalConfig = $base === null ? [] : $base->getConfig();
        $globalConfig['companyName'] = 'Test corp.';
        $globalConfig['displayCompanyAddress'] = true;
        $this->upsertBaseConfig($globalConfig, 'invoice');

        $salesChannelConfig = [
            'companyName' => 'Custom corp.',
            'displayCompanyAddress' => false,
        ];
        $this->upsertBaseConfig($salesChannelConfig, 'invoice', $this->salesChannelContext->getSalesChannel()->getId());

        $operation = new DocumentGenerateOperation($orderId);

        $documentId = $this->documentGenerator->generate('invoice', [$orderId => $operation], $this->context)->first();

        /** @var EntityRepositoryInterface $documentRepository */
        $documentRepository = $this->getContainer()->get('document.repository');
        /** @var DocumentEntity $document */
        $document = $documentRepository->search(new Criteria([$documentId->getId()]), Context::createDefaultContext())->first();

        $expectedConfig = array_merge($globalConfig, $salesChannelConfig);

        $actualConfig = $document->getConfig();
        foreach ($expectedConfig as $key => $value) {
            static::assertArrayHasKey($key, $actualConfig);
            static::assertSame($actualConfig[$key], $value);
        }
    }

    public function testConfigurationWithOverrides(): void
    {
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $base = $this->getBaseConfig('invoice');
        $globalConfig = $base === null ? [] : $base->getConfig();
        $globalConfig['companyName'] = 'Test corp.';
        $globalConfig['displayCompanyAddress'] = true;
        $this->upsertBaseConfig($globalConfig, 'invoice');

        $salesChannelConfig = [
            'companyName' => 'Custom corp.',
            'displayCompanyAddress' => false,
            'pageSize' => 'a5',
        ];
        $this->upsertBaseConfig($salesChannelConfig, 'invoice', $this->salesChannelContext->getSalesChannel()->getId());

        $overrides = [
            'companyName' => 'Override corp.',
            'displayCompanyAddress' => true,
        ];

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, $overrides);

        $documentIdWithOverride = $this->documentGenerator->generate('invoice', [$orderId => $operation], $this->context)->first();

        /** @var EntityRepositoryInterface $documentRepository */
        $documentRepository = $this->getContainer()->get('document.repository');
        /** @var DocumentEntity $document */
        $document = $documentRepository->search(new Criteria([$documentIdWithOverride->getId()]), Context::createDefaultContext())->first();

        $expectedConfig = array_merge($globalConfig, $salesChannelConfig, $overrides);

        $actualConfig = $document->getConfig();
        foreach ($expectedConfig as $key => $value) {
            static::assertArrayHasKey($key, $actualConfig);
            static::assertSame($actualConfig[$key], $value);
        }
    }

    public function testCreateInvoicePdf(): void
    {
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $documentConfiguration = new DocumentConfiguration();
        $documentConfiguration->setDocumentNumber('1001');

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, $documentConfiguration->jsonSerialize());

        $documentInvoice = $this->documentGenerator->generate('invoice', [$orderId => $operation], $this->context)->first();

        static::assertTrue(Uuid::isValid($documentInvoice->getId()));

        $documentRepository = $this->getContainer()->get('document.repository');

        $criteria = new Criteria([$documentInvoice->getId()]);
        $criteria->addAssociation('documentType');

        $document = $documentRepository
            ->search($criteria, $this->context)
            ->get($documentInvoice->getId());

        static::assertNotNull($document);
        static::assertSame($orderId, $document->getOrderId());
        static::assertSame('invoice', $document->getDocumentType()->getTechnicalName());
        static::assertSame(FileTypes::PDF, $document->getFileType());
    }

    public function testGenerate(): void
    {
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $documentConfiguration = new DocumentConfiguration();
        $documentConfiguration->setDocumentNumber('1001');

        $operationInvoice = new DocumentGenerateOperation($orderId, FileTypes::PDF, $documentConfiguration->jsonSerialize());
        $operationDelivery = new DocumentGenerateOperation($orderId, FileTypes::PDF, $documentConfiguration->jsonSerialize());

        $documentIds = [];
        $invoice = $this->documentGenerator->generate('invoice', [$orderId => $operationInvoice], $this->context)->first();

        static::assertNotNull($invoice);
        $documentIds[] = $invoice->getId();

        $delivery = $this->documentGenerator->generate('delivery_note', [$orderId => $operationDelivery], $this->context)->first();

        static::assertNotNull($invoice);
        $documentIds[] = $delivery->getId();

        static::assertCount(2, $documentIds);

        $criteria = new Criteria($documentIds);
        $criteria->addAssociation('documentType');

        $documentRepository = $this->getContainer()->get('document.repository');
        $documents = $documentRepository->search($criteria, $this->context);

        static::assertCount(2, $documents);

        $invoiceDoc = $documents->filter(function (DocumentEntity $doc) {
            return $doc->getDocumentType()->getTechnicalName() === 'invoice';
        })->first();

        static::assertNotNull($invoiceDoc);
        static::assertSame($orderId, $invoiceDoc->getOrderId());
        static::assertSame(FileTypes::PDF, $invoiceDoc->getFileType());

        $deliveryDoc = $documents->filter(function (DocumentEntity $doc) {
            return $doc->getDocumentType()->getTechnicalName() === 'invoice';
        })->first();

        static::assertNotNull($deliveryDoc);
        static::assertSame($orderId, $deliveryDoc->getOrderId());
        static::assertSame(FileTypes::PDF, $deliveryDoc->getFileType());
    }

    public function testCreateInvoiceIsExistingNumberPdf(): void
    {
        $this->expectException(DocumentNumberAlreadyExistsException::class);

        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $documentInvoiceConfiguration = new DocumentConfiguration();
        $documentInvoiceConfiguration->setDocumentNumber('1002');

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, $documentInvoiceConfiguration->jsonSerialize());

        $documentInvoice = $this->documentGenerator->generate('delivery_note', [$orderId => $operation], $this->context)->first();

        static::assertTrue(Uuid::isValid($documentInvoice->getId()));

        $documentRepository = $this->getContainer()->get('document.repository');

        $criteria = new Criteria([$documentInvoice->getId()]);
        $criteria->addAssociation('documentType');

        $document = $documentRepository
            ->search($criteria, $this->context)
            ->get($documentInvoice->getId());

        static::assertNotNull($document);
        static::assertSame($orderId, $document->getOrderId());

        $documentInvoiceConfiguration = new DocumentConfiguration();
        $documentInvoiceConfiguration->setDocumentNumber('1002');

        $operation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::PDF,
            $documentInvoiceConfiguration->jsonSerialize()
        );

        $this->documentGenerator->generate('delivery_note', [$orderId => $operation], $this->context)->first();
    }

    public function testGenerateStaticDocument(): void
    {
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, [], null, true);

        $generatedDocument = $this->documentGenerator->generate('invoice', [$orderId => $operation], $this->context)->first();

        static::assertInstanceOf(DocumentIdStruct::class, $generatedDocument);
        static::assertNotNull($generatedDocument->getId());
        static::assertNotNull($generatedDocument->getDeepLinkCode());
        static::assertNull($generatedDocument->getMediaId());
    }

    public function testGenerateNonStaticDocument(): void
    {
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, [], null, false);

        $generatedDocument = $this->documentGenerator->generate('invoice', [$orderId => $operation], $this->context)->first();

        static::assertInstanceOf(DocumentIdStruct::class, $generatedDocument);
        static::assertNotNull($generatedDocument->getId());
        static::assertNotNull($generatedDocument->getDeepLinkCode());
        static::assertNotNull($generatedDocument->getMediaId());
    }

    public function testReadNonStaticGeneratedDocument(): void
    {
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF);

        $invoiceStruct = $this->documentGenerator->generate('invoice', [$orderId => $operation], $this->context)->first();

        $generatedDocument = $this->documentGenerator->readDocument($invoiceStruct->getId(), $this->context);

        static::assertInstanceOf(RenderedDocument::class, $generatedDocument);
        static::assertNotNull($generatedDocument->getHtml());
        static::assertNotNull($generatedDocument->getContent());
        static::assertEquals(PdfRenderer::FILE_CONTENT_TYPE, $generatedDocument->getContentType());

        $document = $this->getContainer()->get('document.repository')->search(
            new Criteria([$invoiceStruct->getId()]),
            $this->context,
        )->first();

        static::assertNotNull($document);
        $mediaId = $document->getDocumentMediaFileId();

        $media = $this->context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($mediaId) {
            return $this->getContainer()->get(FileLoader::class)->loadMediaResource($mediaId, $context);
        });

        static::assertNotNull($media);
    }

    public function testReadStaticGeneratedDocument(): void
    {
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, [], null, true);

        $invoiceStruct = $this->documentGenerator->generate('invoice', [$orderId => $operation], $this->context)->first();
        static::assertNotNull($invoiceStruct);

        $generatedDocument = $this->documentGenerator->readDocument($invoiceStruct->getId(), $this->context);

        static::assertNull($generatedDocument);
    }

    private function getBaseConfig(string $documentType, ?string $salesChannelId = null): ?DocumentBaseConfigEntity
    {
        /** @var EntityRepositoryInterface $documentTypeRepository */
        $documentTypeRepository = $this->getContainer()->get('document_type.repository');
        $documentTypeId = $documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', $documentType)),
            Context::createDefaultContext()
        )->firstId();

        /** @var EntityRepositoryInterface $documentBaseConfigRepository */
        $documentBaseConfigRepository = $this->getContainer()->get('document_base_config.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('documentTypeId', $documentTypeId));
        $criteria->addFilter(new EqualsFilter('global', true));

        if ($salesChannelId !== null) {
            $criteria->addFilter(new EqualsFilter('salesChannels.salesChannelId', $salesChannelId));
            $criteria->addFilter(new EqualsFilter('salesChannels.documentTypeId', $documentTypeId));
        }

        return $documentBaseConfigRepository->search($criteria, Context::createDefaultContext())->first();
    }

    private function upsertBaseConfig($config, string $documentType, ?string $salesChannelId = null): void
    {
        $baseConfig = $this->getBaseConfig($documentType, $salesChannelId);

        /** @var EntityRepositoryInterface $documentTypeRepository */
        $documentTypeRepository = $this->getContainer()->get('document_type.repository');
        $documentTypeId = $documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', $documentType)),
            Context::createDefaultContext()
        )->firstId();

        if ($baseConfig === null) {
            $documentConfigId = Uuid::randomHex();
        } else {
            $documentConfigId = $baseConfig->getId();
        }

        $data = [
            'id' => $documentConfigId,
            'typeId' => $documentTypeId,
            'documentTypeId' => $documentTypeId,
            'config' => $config,
        ];
        if ($baseConfig === null) {
            $data['name'] = $documentConfigId;
        }
        if ($salesChannelId !== null) {
            $data['salesChannels'] = [
                [
                    'documentBaseConfigId' => $documentConfigId,
                    'documentTypeId' => $documentTypeId,
                    'salesChannelId' => $salesChannelId,
                ],
            ];
        }

        /** @var EntityRepositoryInterface $documentBaseConfigRepository */
        $documentBaseConfigRepository = $this->getContainer()->get('document_base_config.repository');
        $documentBaseConfigRepository->upsert([$data], Context::createDefaultContext());
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
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
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

    private function createDocumentWithFile(): DocumentEntity
    {
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF);
        $documentStruct = $this->documentGenerator->generate('invoice', [$orderId => $operation], $this->context)->first();

        static::assertTrue(Uuid::isValid($documentStruct->getId()));

        $documentRepository = $this->getContainer()->get('document.repository');
        $criteria = new Criteria([$documentStruct->getId()]);
        $criteria->addAssociation('documentMediaFile');
        $criteria->addAssociation('documentType');

        /** @var DocumentEntity $document */
        $document = $documentRepository->search($criteria, $this->context)->get($documentStruct->getId());

        $this->documentGenerator->readDocument($document->getId(), $this->context);

        /** @var DocumentEntity $document */
        $document = $documentRepository->search($criteria, $this->context)->get($documentStruct->getId());

        return $document;
    }
}
