<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\DocumentIdStruct;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentRendererException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\File\FileLoader;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Media\MediaType\BinaryType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
class DocumentGeneratorTest extends TestCase
{
    use AppSystemTestBehaviour;
    use DocumentTrait;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private DocumentGenerator $documentGenerator;

    private EntityRepository $documentRepository;

    private string $documentTypeId;

    private string $orderId;

    protected function setUp(): void
    {
        parent::setUp();

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

        $documentTypeRepository = $this->getContainer()->get('document_type.repository');

        $this->documentTypeId = $documentTypeRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', InvoiceRenderer::TYPE)),
            Context::createDefaultContext()
        )->firstId() ?? '';

        $cart = $this->generateDemoCart(2);
        $this->orderId = $this->persistCart($cart);

        $this->documentRepository = $this->getContainer()->get('document.repository');
    }

    public function testCreateDeliveryNotePdf(): void
    {
        $operation = new DocumentGenerateOperation($this->orderId);

        $documentStruct = $this->documentGenerator->generate(DeliveryNoteRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();
        static::assertNotNull($documentStruct);
        static::assertTrue(Uuid::isValid($documentStruct->getId()));

        $criteria = new Criteria([$documentStruct->getId()]);
        $criteria->addAssociation('documentType');

        /** @var DocumentEntity $document */
        $document = $this->documentRepository
            ->search($criteria, $this->context)
            ->get($documentStruct->getId());

        static::assertNotNull($document);
        static::assertSame($this->orderId, $document->getOrderId());

        static::assertNotNull($document->getDocumentType());
        static::assertSame(Defaults::LIVE_VERSION, $document->getOrderVersionId());
        static::assertSame(DeliveryNoteRenderer::TYPE, $document->getDocumentType()->getTechnicalName());
        static::assertSame(FileTypes::PDF, $document->getFileType());
    }

    public function testGenerateEmpty(): void
    {
        $documentStruct = $this->documentGenerator->generate(DeliveryNoteRenderer::TYPE, [], $this->context)->getSuccess();

        static::assertCount(0, $documentStruct);

        $invalidOrderId = Uuid::randomHex();

        $documentStruct = $this->documentGenerator->generate(DeliveryNoteRenderer::TYPE, [
            $invalidOrderId => new DocumentGenerateOperation($invalidOrderId),
        ], $this->context)->getSuccess();

        static::assertCount(0, $documentStruct);
    }

    public function testPreviewWithIncorrectDeepLinkCode(): void
    {
        $this->expectException(DocumentException::class);

        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')->search(new Criteria([$this->orderId]), $this->context)->first();

        $operation = new DocumentGenerateOperation($this->orderId);
        $operation->assign([
            'preview' => true,
        ]);
        $documentStruct = $this->documentGenerator->preview(InvoiceRenderer::TYPE, $operation, $order->getDeepLinkCode() ?? '', $this->context);

        static::assertNotEmpty($documentStruct->getContent());

        $operation = new DocumentGenerateOperation(Uuid::randomHex());

        $this->documentGenerator->preview(InvoiceRenderer::TYPE, $operation, '', $this->context);
    }

    public function testPreviewInvoice(): void
    {
        $order = $this->getContainer()->get('order.repository')->search(new Criteria([$this->orderId]), $this->context)->first();
        static::assertNotNull($order);
        static::assertInstanceOf(OrderEntity::class, $order);

        $operation = new DocumentGenerateOperation($this->orderId);

        $documentStruct = $this->documentGenerator->preview(InvoiceRenderer::TYPE, $operation, (string) $order->getDeepLinkCode(), $this->context);

        static::assertNotEmpty($documentStruct->getContent());
    }

    public function testPreviewStorno(): void
    {
        $order = $this->getContainer()->get('order.repository')->search(new Criteria([$this->orderId]), $this->context)->first();
        static::assertNotNull($order);
        static::assertInstanceOf(OrderEntity::class, $order);
        $orderCustomer = $order->getOrderCustomer();
        static::assertNotNull($orderCustomer);
        $customerNo = (string) $orderCustomer->getCustomerNumber();

        $invoiceNumber = '9998';
        $invoiceConfig1 = new DocumentConfiguration();
        $invoiceConfig1->setDocumentNumber($invoiceNumber);

        $invoiceConfig2 = new DocumentConfiguration();
        $invoiceConfig2->setDocumentNumber('9999');

        $operation1 = new DocumentGenerateOperation($this->orderId, FileTypes::PDF, $invoiceConfig1->jsonSerialize());
        $operation2 = new DocumentGenerateOperation($this->orderId, FileTypes::PDF, $invoiceConfig2->jsonSerialize());

        $this->documentGenerator->generate(InvoiceRenderer::TYPE, [
            $this->orderId => $operation1,
        ], $this->context);

        $this->documentGenerator->generate(InvoiceRenderer::TYPE, [
            $this->orderId => $operation2,
        ], $this->context);

        $stornoConfiguration = new DocumentConfiguration();
        $stornoConfiguration->assign([
            'custom' => [
                'invoiceNumber' => $invoiceNumber,
            ],
        ]);

        $operation = new DocumentGenerateOperation(
            $this->orderId,
            FileTypes::PDF,
            $stornoConfiguration->jsonSerialize(),
            null,
            false,
            true
        );

        $stornoStruct = $this->documentGenerator->preview(StornoRenderer::TYPE, $operation, (string) $order->getDeepLinkCode(), $this->context);

        static::assertNotEmpty($stornoStruct->getContent());
        static::assertStringContainsString('Cancellation 1000 for Invoice ' . $invoiceNumber, $stornoStruct->getHtml());
        static::assertStringContainsString('Customer no. ' . $customerNo, $stornoStruct->getHtml());

        $this->getContainer()->get('order_customer.repository')->update([[
            'id' => $orderCustomer->getId(),
            'customerNumber' => 'CHANGED NUMBER',
        ]], $this->context);

        $stornoStruct = $this->documentGenerator->preview(StornoRenderer::TYPE, $operation, (string) $order->getDeepLinkCode(), $this->context);

        static::assertStringContainsString('Cancellation 1000 for Invoice ' . $invoiceNumber, $stornoStruct->getHtml());
        // Customer no does not change because it refers to the older version of order
        static::assertStringContainsString('Customer no. ' . $customerNo, $stornoStruct->getHtml());
    }

    #[DataProvider('uploadDataProvider')]
    public function testUpload(bool $preGenerateDoc, Request $uploadFileRequest, bool $static = true, ?\Exception $expectedException = null): void
    {
        if ($expectedException instanceof \Exception) {
            static::expectExceptionObject($expectedException);
        }

        if ($preGenerateDoc) {
            $operation = new DocumentGenerateOperation($this->orderId);

            $documents = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess();
            $document = $documents->first();

            static::assertNotNull($document);

            $documentId = $document->getId();
        } else {
            $documentId = Uuid::randomHex();

            $this->documentRepository->create([[
                'id' => $documentId,
                'documentTypeId' => $this->documentTypeId,
                'fileType' => FileTypes::PDF,
                'orderId' => $this->orderId,
                'static' => $static,
                'config' => [],
                'documentMediaFileId' => null,
                'deepLinkCode' => Random::getAlphanumericString(32),
            ]], $this->context);
        }

        if (!$static) {
            $this->documentRepository->update([[
                'id' => $documentId,
                'documentMediaFileId' => null,
                'static' => false,
            ]], $this->context);
        }

        $this->documentGenerator->upload($documentId, $this->context, $uploadFileRequest);

        /** @var DocumentEntity $document */
        $document = $this->documentRepository->search(new Criteria([$documentId]), $this->context)->get($documentId);

        static::assertNotNull($document);
        static::assertNotNull($document->getDocumentMediaFileId());

        $savedContent = $this->getContainer()->get(MediaService::class)->loadFile($document->getDocumentMediaFileId(), $this->context);
        static::assertEquals($uploadFileRequest->getContent(), $savedContent);
    }

    public static function uploadDataProvider(): \Generator
    {
        yield 'upload successfully' => [
            false,
            new Request([
                'extension' => FileTypes::PDF,
                'fileName' => 'test',
            ], [], [], [], [], [
                'HTTP_CONTENT_LENGTH' => \strlen('this is some content'),
            ], 'this is some content'),
            true,
            null,
        ];

        yield 'upload without filename' => [
            false,
            new Request([
                'extension' => FileTypes::PDF,
            ]),
            true,
            new DocumentGenerationException('Parameter "fileName" is missing'),
        ];

        yield 'upload non static document' => [
            true,
            new Request(),
            false,
            new DocumentGenerationException('This document is dynamically generated and cannot be overwritten'),
        ];

        yield 'upload with existed media' => [
            true,
            new Request(),
            true,
            new DocumentGenerationException('Document already exists'),
        ];
    }

    public function testInvoiceWithComment(): void
    {
        $comment = 'this is a comment';
        $operation = new DocumentGenerateOperation($this->orderId, FileTypes::PDF, ['documentComment' => $comment]);

        $documentStruct = $this->documentGenerator->generate(DeliveryNoteRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();

        static::assertNotNull($documentStruct);
        static::assertTrue(Uuid::isValid($documentStruct->getId()));

        $criteria = new Criteria([$documentStruct->getId()]);
        $criteria->addAssociation('documentType');

        /** @var DocumentEntity $document */
        $document = $this->documentRepository
            ->search($criteria, $this->context)
            ->get($documentStruct->getId());

        static::assertNotNull($document);
        static::assertSame($this->orderId, $document->getOrderId());

        $config = DocumentConfigurationFactory::createConfiguration($document->getConfig());

        static::assertNotNull($config->getDocumentDate());
        static::assertSame($comment, $config->getDocumentComment());
        static::assertNotNull($config->getDocumentNumber());

        static::assertNotNull($document->getDocumentType());
        static::assertSame(Defaults::LIVE_VERSION, $document->getOrderVersionId());
        static::assertSame(DeliveryNoteRenderer::TYPE, $document->getDocumentType()->getTechnicalName());
        static::assertSame(FileTypes::PDF, $document->getFileType());
    }

    public function testCreateStornoBillReferencingInvoice(): void
    {
        $operation = new DocumentGenerateOperation($this->orderId);

        $invoiceStruct = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();

        static::assertNotNull($invoiceStruct);
        static::assertTrue(Uuid::isValid($invoiceStruct->getId()));

        /** @var DocumentEntity $invoice */
        $invoice = $this->documentRepository->search(new Criteria([$invoiceStruct->getId()]), $this->context)->get($invoiceStruct->getId());

        static::assertNotNull($invoice);
        // create a cancellation invoice which references the invoice
        $operation = new DocumentGenerateOperation($this->orderId, FileTypes::PDF, [], $invoice->getId());

        $stornoStruct = $this->documentGenerator->generate(StornoRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();

        static::assertNotNull($stornoStruct);
        static::assertTrue(Uuid::isValid($stornoStruct->getId()));

        /** @var DocumentEntity $storno */
        $storno = $this->documentRepository->search(new Criteria([$stornoStruct->getId()]), $this->context)->get($stornoStruct->getId());

        static::assertNotNull($storno);
        static::assertEquals($invoice->getId(), $storno->getReferencedDocumentId());
        static::assertSame($storno->getOrderVersionId(), $invoice->getOrderVersionId());
    }

    #[Group('slow')]
    public function testCreateFileIsWrittenInFs(): void
    {
        /** @var FilesystemOperator $fileSystem */
        $fileSystem = $this->getContainer()->get('shopware.filesystem.private');
        $document = $this->createDocumentWithFile();

        static::assertNotNull($document->getDocumentMediaFile());
        $filePath = $document->getDocumentMediaFile()->getPath();

        static::assertTrue($fileSystem->has($filePath));
        $fileSystem->delete($filePath);
        static::assertFalse($fileSystem->has($filePath));
    }

    public function testReadDocumentFileWithInvalidDocumentId(): void
    {
        $documentId = Uuid::randomHex();

        static::expectException(DocumentException::class);
        static::expectExceptionMessage(\sprintf('The document with id "%s" is invalid or could not be found.', $documentId));

        $this->documentGenerator->readDocument($documentId, $this->context);
    }

    public function testReadDocumentFileWithIncorrectDeepLinkCode(): void
    {
        $documentId = Uuid::randomHex();

        static::expectException(DocumentException::class);
        static::expectExceptionMessage(\sprintf('The document with id "%s" is invalid or could not be found.', $documentId));

        /** @var FilesystemOperator $fileSystem */
        $fileSystem = $this->getContainer()->get('shopware.filesystem.private');

        $documentTypeRepository = $this->getContainer()->get('document_type.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', DeliveryNoteRenderer::TYPE));

        $documentType = $documentTypeRepository->search($criteria, $this->context)->first();

        static::assertNotNull($documentType);
        static::assertInstanceOf(DocumentTypeEntity::class, $documentType);

        $mediaId = Uuid::randomHex();
        $this->documentRepository->create(
            [
                [
                    'id' => $documentId,
                    'documentTypeId' => $documentType->getId(),
                    'fileType' => FileTypes::PDF,
                    'orderId' => $this->orderId,
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
        $document = $this->documentRepository->search($criteria, $this->context)->get($documentId);

        static::assertNotNull($document->getDocumentMediaFile());
        $filePath = $document->getDocumentMediaFile()->getPath();

        $fileSystem->write($filePath, 'test123');

        static::assertTrue($fileSystem->has($filePath));

        $this->documentGenerator->readDocument($document->getId(), $this->context, 'wrong code');
    }

    public function testConfigurationWithSalesChannelOverride(): void
    {
        $base = $this->getBaseConfig(InvoiceRenderer::TYPE);
        $globalConfig = $base instanceof DocumentBaseConfigEntity ? $base->getConfig() : [];
        $globalConfig['companyName'] = 'Test corp.';
        $globalConfig['displayCompanyAddress'] = true;
        $this->upsertBaseConfig($globalConfig, InvoiceRenderer::TYPE);

        $salesChannelConfig = [
            'companyName' => 'Custom corp.',
            'displayCompanyAddress' => false,
        ];
        $this->upsertBaseConfig($salesChannelConfig, InvoiceRenderer::TYPE, $this->salesChannelContext->getSalesChannel()->getId());

        $operation = new DocumentGenerateOperation($this->orderId);

        $documentId = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();
        static::assertNotNull($documentId);

        /** @var DocumentEntity $document */
        $document = $this->documentRepository->search(new Criteria([$documentId->getId()]), Context::createDefaultContext())->first();

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

        $base = $this->getBaseConfig(InvoiceRenderer::TYPE);
        $globalConfig = $base instanceof DocumentBaseConfigEntity ? $base->getConfig() : [];
        $globalConfig['companyName'] = 'Test corp.';
        $globalConfig['displayCompanyAddress'] = true;
        $this->upsertBaseConfig($globalConfig, InvoiceRenderer::TYPE);

        $salesChannelConfig = [
            'companyName' => 'Custom corp.',
            'displayCompanyAddress' => false,
            'pageSize' => 'a5',
        ];
        $this->upsertBaseConfig($salesChannelConfig, InvoiceRenderer::TYPE, $this->salesChannelContext->getSalesChannel()->getId());

        $overrides = [
            'companyName' => 'Override corp.',
            'displayCompanyAddress' => true,
        ];

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, $overrides);

        $documentIdWithOverride = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $operation], $this->context)->getSuccess()->first();
        static::assertNotNull($documentIdWithOverride);

        $document = $this->documentRepository->search(new Criteria([$documentIdWithOverride->getId()]), Context::createDefaultContext())->first();
        static::assertNotNull($document);
        static::assertInstanceOf(DocumentEntity::class, $document);

        $expectedConfig = array_merge($globalConfig, $salesChannelConfig, $overrides);

        $actualConfig = $document->getConfig();
        foreach ($expectedConfig as $key => $value) {
            static::assertArrayHasKey($key, $actualConfig);
            static::assertSame($actualConfig[$key], $value);
        }
    }

    public function testCreateInvoicePdf(): void
    {
        $documentConfiguration = new DocumentConfiguration();
        $documentConfiguration->setDocumentNumber('1001');

        $operation = new DocumentGenerateOperation($this->orderId, FileTypes::PDF, $documentConfiguration->jsonSerialize());

        $documentInvoice = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();

        static::assertNotNull($documentInvoice);
        static::assertTrue(Uuid::isValid($documentInvoice->getId()));

        $criteria = new Criteria([$documentInvoice->getId()]);
        $criteria->addAssociation('documentType');

        /** @var DocumentEntity $document */
        $document = $this->documentRepository
            ->search($criteria, $this->context)
            ->get($documentInvoice->getId());

        static::assertNotNull($document);
        static::assertSame($this->orderId, $document->getOrderId());

        // document should refer to a versioned order
        static::assertNotEquals(Defaults::LIVE_VERSION, $document->getOrderVersionId());
        static::assertEquals($operation->getOrderVersionId(), $document->getOrderVersionId());

        static::assertNotNull($document->getDocumentType());
        static::assertSame(InvoiceRenderer::TYPE, $document->getDocumentType()->getTechnicalName());
        static::assertSame(FileTypes::PDF, $document->getFileType());
    }

    public function testGenerateWithInvalidType(): void
    {
        static::expectException(InvalidDocumentRendererException::class);
        static::expectExceptionMessage('Unable to find a document renderer with type "invalid_type"');
        $this->documentGenerator->generate('invalid_type', [], $this->context);
    }

    public function testGenerate(): void
    {
        $orderId = $this->orderId;
        $documentConfiguration = new DocumentConfiguration();
        $documentConfiguration->setDocumentNumber('1001');

        $operationInvoice = new DocumentGenerateOperation($orderId, FileTypes::PDF, $documentConfiguration->jsonSerialize());
        $operationDelivery = new DocumentGenerateOperation($orderId, FileTypes::PDF, $documentConfiguration->jsonSerialize());

        $documentIds = [];
        $invoice = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $operationInvoice], $this->context)->getSuccess()->first();

        static::assertNotNull($invoice);
        $documentIds[] = $invoice->getId();

        $delivery = $this->documentGenerator->generate(DeliveryNoteRenderer::TYPE, [$orderId => $operationDelivery], $this->context)->getSuccess()->first();

        static::assertNotNull($delivery);
        $documentIds[] = $delivery->getId();

        static::assertCount(2, $documentIds);

        $criteria = new Criteria($documentIds);
        $criteria->addAssociation('documentType');

        $documents = $this->documentRepository->search($criteria, $this->context);

        static::assertCount(2, $documents);

        $invoiceDoc = $documents->filter(function (DocumentEntity $doc) {
            $type = $doc->getDocumentType();
            static::assertNotNull($type);

            return $type->getTechnicalName() === InvoiceRenderer::TYPE;
        })->first();

        static::assertNotNull($invoiceDoc);
        static::assertInstanceOf(DocumentEntity::class, $invoiceDoc);
        static::assertSame($orderId, $invoiceDoc->getOrderId());
        static::assertSame(FileTypes::PDF, $invoiceDoc->getFileType());

        $deliveryDoc = $documents->filter(function (DocumentEntity $doc) {
            $type = $doc->getDocumentType();
            static::assertNotNull($type);

            return $type->getTechnicalName() === InvoiceRenderer::TYPE;
        })->first();

        static::assertNotNull($deliveryDoc);
        static::assertInstanceOf(DocumentEntity::class, $deliveryDoc);
        static::assertSame($orderId, $deliveryDoc->getOrderId());
        static::assertSame(FileTypes::PDF, $deliveryDoc->getFileType());
    }

    public function testGenerateDuplicatedDocumentNumber(): void
    {
        $documentConfiguration = new DocumentConfiguration();
        $documentConfiguration->setDocumentNumber('1001');

        $operationInvoice = new DocumentGenerateOperation($this->orderId, FileTypes::PDF, $documentConfiguration->jsonSerialize());
        $result = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$this->orderId => $operationInvoice], $this->context);

        static::assertEmpty($result->getErrors());
        static::assertNotEmpty($result->getSuccess()->getElements());

        $result = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$this->orderId => $operationInvoice], $this->context);
        static::assertEmpty($result->getSuccess()->getElements());
        static::assertNotEmpty($result->getErrors());
        static::assertArrayHasKey($this->orderId, $result->getErrors());
        static::assertEquals('Document number 1001 has already been allocated.', $result->getErrors()[$this->orderId]->getMessage());
    }

    public function testCreateInvoiceIsExistingNumberPdf(): void
    {
        $documentInvoiceConfiguration = new DocumentConfiguration();
        $documentInvoiceConfiguration->setDocumentNumber('1002');

        $operation = new DocumentGenerateOperation($this->orderId, FileTypes::PDF, $documentInvoiceConfiguration->jsonSerialize());

        $documentInvoice = $this->documentGenerator->generate(DeliveryNoteRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();

        static::assertNotNull($documentInvoice);
        static::assertTrue(Uuid::isValid($documentInvoice->getId()));

        $criteria = new Criteria([$documentInvoice->getId()]);
        $criteria->addAssociation('documentType');

        /** @var DocumentEntity $document */
        $document = $this->documentRepository
            ->search($criteria, $this->context)
            ->get($documentInvoice->getId());

        static::assertNotNull($document);
        static::assertSame($this->orderId, $document->getOrderId());

        $documentInvoiceConfiguration = new DocumentConfiguration();
        $documentInvoiceConfiguration->setDocumentNumber('1002');

        $operation = new DocumentGenerateOperation(
            $this->orderId,
            FileTypes::PDF,
            $documentInvoiceConfiguration->jsonSerialize()
        );

        $errors = $this->documentGenerator->generate(DeliveryNoteRenderer::TYPE, [$this->orderId => $operation], $this->context)->getErrors();
        static::assertNotEmpty($errors);
        static::assertArrayHasKey($this->orderId, $errors);
        static::assertSame($errors[$this->orderId]->getMessage(), 'Document number 1002 has already been allocated.');
    }

    public function testGenerateStaticDocument(): void
    {
        $operation = new DocumentGenerateOperation($this->orderId, FileTypes::PDF, [], null, true);

        $generatedDocument = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();

        static::assertInstanceOf(DocumentIdStruct::class, $generatedDocument);
        static::assertNull($generatedDocument->getMediaId());
    }

    public function testGenerateNonStaticDocument(): void
    {
        $operation = new DocumentGenerateOperation($this->orderId, FileTypes::PDF, [], null, false);

        $generatedDocument = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();

        static::assertInstanceOf(DocumentIdStruct::class, $generatedDocument);
        static::assertNotNull($generatedDocument->getMediaId());
    }

    public function testReadNonStaticGeneratedDocument(): void
    {
        $operation = new DocumentGenerateOperation($this->orderId, FileTypes::PDF);

        $invoiceStruct = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();
        static::assertNotNull($invoiceStruct);
        $generatedDocument = $this->documentGenerator->readDocument($invoiceStruct->getId(), $this->context);

        static::assertInstanceOf(RenderedDocument::class, $generatedDocument);
        static::assertEquals(PdfRenderer::FILE_CONTENT_TYPE, $generatedDocument->getContentType());

        $document = $this->documentRepository->search(
            new Criteria([$invoiceStruct->getId()]),
            $this->context,
        )->first();

        static::assertNotNull($document);
        static::assertInstanceOf(DocumentEntity::class, $document);
        $mediaId = $document->getDocumentMediaFileId();

        static::assertNotNull($mediaId);

        $media = $this->context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->getContainer()->get(FileLoader::class)->loadMediaFileStream($mediaId, $context));

        static::assertInstanceOf(StreamInterface::class, $media);
    }

    public function testReadStaticGeneratedDocument(): void
    {
        $operation = new DocumentGenerateOperation($this->orderId, FileTypes::PDF, [], null, true);

        $invoiceStruct = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();
        static::assertNotNull($invoiceStruct);

        $generatedDocument = $this->documentGenerator->readDocument($invoiceStruct->getId(), $this->context);

        static::assertNull($generatedDocument);
    }

    public function testReadNonStaticDocumentWithMissingFile(): void
    {
        $documentId = Uuid::randomHex();

        $this->documentRepository->create([[
            'id' => $documentId,
            'documentTypeId' => $this->documentTypeId,
            'fileType' => FileTypes::PDF,
            'orderId' => $this->orderId,
            'static' => false,
            'documentMediaFileId' => null,
            'config' => [],
            'deepLinkCode' => Random::getAlphanumericString(32),
        ]], $this->context);

        $generatedDocument = $this->documentGenerator->readDocument($documentId, $this->context);

        static::assertInstanceOf(RenderedDocument::class, $generatedDocument);
        static::assertEquals(PdfRenderer::FILE_CONTENT_TYPE, $generatedDocument->getContentType());

        $document = $this->documentRepository->search(
            new Criteria([$documentId]),
            $this->context,
        )->first();

        static::assertNotNull($document);
        static::assertInstanceOf(DocumentEntity::class, $document);
        $mediaId = $document->getDocumentMediaFileId();
        static::assertNotNull($mediaId);

        $media = $this->context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->getContainer()->get(FileLoader::class)->loadMediaFileStream($mediaId, $context));

        static::assertNotNull($media);
    }

    #[DataProvider('readDocumentDataProvider')]
    public function testReadDocument(bool $withMedia, bool $static): void
    {
        $operation = new DocumentGenerateOperation($this->orderId, FileTypes::PDF, [], null, $static);

        $invoiceStruct = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();
        static::assertNotNull($invoiceStruct);
        static::assertInstanceOf(DocumentIdStruct::class, $invoiceStruct);
        $documentMediaFileId = $invoiceStruct->getMediaId();
        $documentId = $invoiceStruct->getId();

        if ($static && $withMedia === false) {
            $generatedDocument = $this->documentGenerator->readDocument($invoiceStruct->getId(), $this->context);

            static::assertNull($generatedDocument);

            return;
        }

        $staticFileContent = null;
        if ($static) {
            $staticFileContent = 'this is some content';

            $uploadFileRequest = new Request([
                'extension' => FileTypes::PDF,
                'fileName' => 'test',
            ], [], [], [], [], [
                'HTTP_CONTENT_LENGTH' => \strlen($staticFileContent),
                'HTTP_CONTENT_TYPE' => 'application/pdf',
            ], $staticFileContent);

            $documentMediaFileStruct = $this->documentGenerator->upload($invoiceStruct->getId(), $this->context, $uploadFileRequest);

            $documentMediaFileId = $documentMediaFileStruct->getMediaId();
        }

        static::assertNotNull($documentMediaFileId);

        if ($withMedia === false) {
            $fileSystem = $this->getContainer()->get('shopware.filesystem.private');
            $mediaRepository = $this->getContainer()->get('media.repository');
            /** @var MediaEntity $media */
            $media = $mediaRepository->search(new Criteria([$documentMediaFileId]), $this->context)->get($documentMediaFileId);

            static::assertNotNull($media);

            $filePath = $media->getPath();

            static::assertTrue($fileSystem->has($filePath));
            $fileSystem->delete($filePath);

            $this->documentRepository->update([[
                'id' => $documentId,
                'documentMediaFileId' => null,
            ]], $this->context);

            $mediaRepository->delete([[
                'id' => $documentMediaFileId,
            ]], $this->context);
        }

        $generatedDocument = $this->documentGenerator->readDocument($documentId, $this->context);

        static::assertInstanceOf(RenderedDocument::class, $generatedDocument);

        if ($staticFileContent) {
            static::assertEquals($staticFileContent, $generatedDocument->getContent());
        }

        $document = $this->documentRepository->search(
            new Criteria([$invoiceStruct->getId()]),
            $this->context,
        )->first();

        static::assertNotNull($document);
        static::assertInstanceOf(DocumentEntity::class, $document);

        $mediaId = $document->getDocumentMediaFileId();

        static::assertNotNull($mediaId);

        $media = $this->context->scope(Context::SYSTEM_SCOPE, fn (Context $context) => $this->getContainer()->get(FileLoader::class)->loadMediaFileStream($mediaId, $context));

        static::assertNotNull($media);
    }

    public function testGenerateAndKeepOrderVersionId(): void
    {
        $operation = new DocumentGenerateOperation($this->orderId);

        $documentStruct = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();
        static::assertNotNull($documentStruct);
        static::assertTrue(Uuid::isValid($documentStruct->getId()));

        $criteria = new Criteria([$documentStruct->getId()]);
        $criteria->addAssociation('documentType');

        /** @var DocumentEntity $document */
        $document = $this->documentRepository
            ->search($criteria, $this->context)
            ->get($documentStruct->getId());

        $versionContext = $this->context->createWithVersionId($document->getOrderVersionId());
        static::assertSame($versionContext->getVersionId(), $document->getOrderVersionId());

        // Update the document and keep the orderVersionId value intact
        $this->documentRepository->upsert([[
            'id' => $document->getId(),
            'sent' => true,
        ]], $versionContext);

        /** @var DocumentEntity $document */
        $document = $this->documentRepository
            ->search($criteria, $this->context)
            ->get($documentStruct->getId());

        static::assertSame($versionContext->getVersionId(), $document->getOrderVersionId());
    }

    public static function readDocumentDataProvider(): \Generator
    {
        yield 'read static document' => [
            true,
            true,
        ];
        yield 'read non static document with media' => [
            true,
            false,
        ];
        yield 'read non static document without media' => [
            false,
            false,
        ];
    }

    private function createDocumentWithFile(): DocumentEntity
    {
        $operation = new DocumentGenerateOperation($this->orderId, FileTypes::PDF);
        $documentStruct = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$this->orderId => $operation], $this->context)->getSuccess()->first();

        static::assertNotNull($documentStruct);
        static::assertTrue(Uuid::isValid($documentStruct->getId()));

        $criteria = new Criteria([$documentStruct->getId()]);
        $criteria->addAssociation('documentMediaFile');
        $criteria->addAssociation('documentType');

        /** @var DocumentEntity $document */
        $document = $this->documentRepository->search($criteria, $this->context)->get($documentStruct->getId());

        $this->documentGenerator->readDocument($document->getId(), $this->context);

        /** @var DocumentEntity $document */
        $document = $this->documentRepository->search($criteria, $this->context)->get($documentStruct->getId());
        static::assertNotNull($document);

        return $document;
    }
}
