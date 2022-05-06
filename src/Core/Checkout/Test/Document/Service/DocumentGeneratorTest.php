<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\Service;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Checkout\Document\Exception\DocumentNumberAlreadyExistsException;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Test\Document\DocumentTrait;
use Shopware\Core\Content\Media\File\FileLoader;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Media\MediaType\BinaryType;
use Shopware\Core\Content\Media\Pathname\UrlGenerator;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

class DocumentGeneratorTest extends TestCase
{
    use DocumentTrait;

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

        $documentStruct = $this->documentGenerator->generate(DeliveryNoteRenderer::TYPE, [$orderId => $operation], $this->context)->first();

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

    public function testGenerateEmpty(): void
    {
        $documentStruct = $this->documentGenerator->generate(DeliveryNoteRenderer::TYPE, [], $this->context);
        static::assertCount(0, $documentStruct);
    }

    public function testPreview(): void
    {
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);
        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')->search(new Criteria([$orderId]), $this->context)->first();

        $operation = new DocumentGenerateOperation($orderId);

        $documentStruct = $this->documentGenerator->preview(InvoiceRenderer::TYPE, $operation, $order->getDeepLinkCode(), $this->context);

        static::assertInstanceOf(RenderedDocument::class, $documentStruct);
        static::assertNotEmpty($documentStruct->getContent());
    }

    public function testUploadWithExistMedia(): void
    {
        static::expectException(DocumentGenerationException::class);
        static::expectExceptionMessage('Unable to generate document. Document already exists');
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId);

        $documents = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $operation], $this->context);
        $document = $documents->first();

        static::assertNotNull($document);
        $uploadFileRequest = new Request();
        $this->documentGenerator->upload($document->getId(), $this->context, $uploadFileRequest);
    }

    public function testUploadWithoutFileName(): void
    {
        static::expectException(DocumentGenerationException::class);
        static::expectExceptionMessage('Unable to generate document. Parameter "fileName" is missing');

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $documentRepository = $this->getContainer()->get('document.repository');
        $documentId = Uuid::randomHex();
        $documentTypeRepository = $this->getContainer()->get('document_type.repository');
        $documentTypeId = $documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', InvoiceRenderer::TYPE)),
            Context::createDefaultContext()
        )->firstId();

        $documentRepository->create([[
            'id' => $documentId,
            'documentTypeId' => $documentTypeId,
            'fileType' => FileTypes::PDF,
            'orderId' => $orderId,
            'static' => true,
            'config' => [],
            'documentMediaFileId' => null,
            'deepLinkCode' => Random::getAlphanumericString(32),
        ]], $this->context);

        $uploadFileRequest = new Request([
            'extension' => FileTypes::PDF,
        ]);
        $this->documentGenerator->upload($documentId, $this->context, $uploadFileRequest);
    }

    public function testUploadSuccessfully(): void
    {
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $documentRepository = $this->getContainer()->get('document.repository');
        $documentId = Uuid::randomHex();
        $documentTypeRepository = $this->getContainer()->get('document_type.repository');
        $documentTypeId = $documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', InvoiceRenderer::TYPE)),
            Context::createDefaultContext()
        )->firstId();

        $documentContent = 'this is document content';
        $documentRepository->create([[
            'id' => $documentId,
            'documentTypeId' => $documentTypeId,
            'fileType' => FileTypes::PDF,
            'orderId' => $orderId,
            'static' => true,
            'config' => [],
            'documentMediaFileId' => null,
            'deepLinkCode' => Random::getAlphanumericString(32),
        ]], $this->context);

        $uploadFileRequest = new Request([
            'extension' => FileTypes::PDF,
            'fileName' => 'test',
        ], [], [], [], [], [
            'HTTP_CONTENT_LENGTH' => \strlen($documentContent),
        ], $documentContent);

        $this->documentGenerator->upload($documentId, $this->context, $uploadFileRequest);

        $document = $documentRepository->search(new Criteria([$documentId]), $this->context)->get($documentId);

        static::assertNotNull($document);
        static::assertNotNull($document->getDocumentMediaFileId());

        $savedContent = $this->getContainer()->get(MediaService::class)->loadFile($document->getDocumentMediaFileId(), $this->context);
        static::assertEquals($documentContent, $savedContent);
    }

    public function testUploadToNonStaticDocument(): void
    {
        static::expectException(DocumentGenerationException::class);
        static::expectExceptionMessage('This document is dynamically generated and cannot be overwritten');

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId);

        $documents = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $operation], $this->context);
        $document = $documents->first();

        static::assertNotNull($document);

        $documentRepository = $this->getContainer()->get('document.repository');

        $documentRepository->update([[
            'id' => $document->getId(),
            'documentMediaFileId' => null,
            'static' => false,
        ]], $this->context);

        $uploadFileRequest = new Request();
        $this->documentGenerator->upload($document->getId(), $this->context, $uploadFileRequest);
    }

    public function testInvoiceWithComment(): void
    {
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $comment = 'this is a comment';
        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, ['documentComment' => $comment]);

        $documentStruct = $this->documentGenerator->generate(DeliveryNoteRenderer::TYPE, [$orderId => $operation], $this->context)->first();

        static::assertTrue(Uuid::isValid($documentStruct->getId()));

        $documentRepository = $this->getContainer()->get('document.repository');

        $criteria = new Criteria([$documentStruct->getId()]);
        $criteria->addAssociation('documentType');

        $document = $documentRepository
            ->search($criteria, $this->context)
            ->get($documentStruct->getId());

        static::assertNotNull($document);
        static::assertSame($orderId, $document->getOrderId());

        $config = DocumentConfigurationFactory::createConfiguration($document->getConfig());

        static::assertNotNull($config->getDocumentDate());
        static::assertSame($comment, $config->getDocumentComment());
        static::assertNotNull($config->getDocumentNumber());

        static::assertSame(Defaults::LIVE_VERSION, $document->getOrderVersionId());
        static::assertSame(DeliveryNoteRenderer::TYPE, $document->getDocumentType()->getTechnicalName());
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
            return $this->getContainer()->get(FileLoader::class)->loadMediaFileStream($mediaId, $context);
        });

        static::assertNotNull($media->getContents());
    }

    public function testReadStaticGeneratedDocument(): void
    {
        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, [], null, true);

        $invoiceStruct = $this->documentGenerator->generate('invoice', [$orderId => $operation], $this->context)->first();
        static::assertNotNull($invoiceStruct);

        $generatedDocument = $this->documentGenerator->readDocument($invoiceStruct->getId(), $this->context);

        static::assertNull($generatedDocument);
    }

    private function createDocumentWithFile(): DocumentEntity
    {
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
