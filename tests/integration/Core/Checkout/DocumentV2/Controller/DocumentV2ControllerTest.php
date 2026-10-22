<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator as LegacyDocumentGenerator;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentPersister;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentV2ControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DocumentV2Trait;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<DocumentFileCollection>
     */
    private EntityRepository $documentFileRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $shippingAddressId = Uuid::randomHex();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(
                    ['defaultShippingAddressId' => $shippingAddressId],
                    $this->buildDemoShippingAddress($shippingAddressId),
                ),
            ],
        );

        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->documentFileRepository = static::getContainer()->get('document_file.repository');
    }

    public function testAvailableTypesReturnsImplementedDocumentTypesAndFormats(): void
    {
        $this->getBrowser()->jsonRequest('GET', '/api/_action/order/document-v2/available-types');

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($payload['documentTypes'] ?? null);
        static::assertArrayHasKey(DocumentType::INVOICE->value, $payload['documentTypes']);
        static::assertArrayHasKey(DocumentType::DELIVERY_NOTE->value, $payload['documentTypes']);
        static::assertIsArray($payload['documentTypes'][DocumentType::INVOICE->value]);
        static::assertIsArray($payload['documentTypes'][DocumentType::INVOICE->value]['formats'] ?? null);
        static::assertEqualsCanonicalizing(
            [
                DocumentFormat::HTML->value,
                DocumentFormat::PDF->value,
                DocumentFormat::ZUGFERD_EMBEDDED_PDF->value,
                DocumentFormat::ZUGFERD_XML->value,
            ],
            array_values($payload['documentTypes'][DocumentType::INVOICE->value]['formats']),
        );
        static::assertIsArray($payload['documentTypes'][DocumentType::DELIVERY_NOTE->value]['formats'] ?? null);
        static::assertEqualsCanonicalizing(
            [
                DocumentFormat::HTML->value,
                DocumentFormat::PDF->value,
            ],
            array_values($payload['documentTypes'][DocumentType::DELIVERY_NOTE->value]['formats']),
        );
    }

    public function testPreviewRendersRequestedFormat(): void
    {
        $this->seedDemoBaseConfig(DocumentType::INVOICE->value);

        $orderId = $this->createDraftOrder();

        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_action/order/document-v2/preview',
            [
                'orderId' => $orderId,
                'documentType' => DocumentType::INVOICE->value,
                'format' => DocumentFormat::HTML->value,
                'documentNumber' => self::DOCUMENT_NUMBER,
                'documentDate' => self::DOCUMENT_DATE,
                'documentComment' => 'comment.',
            ],
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $contentType = $response->headers->get('content-type');
        static::assertIsString($contentType);
        static::assertSame(DocumentFormat::HTML->mimeType(), explode(';', $contentType)[0]);
        static::assertStringStartsWith('inline;', (string) $response->headers->get('content-disposition'));

        $content = (string) $response->getContent();
        static::assertStringContainsString('<html', $content);
        static::assertStringContainsString(self::DOCUMENT_NUMBER, $content);
    }

    public function testCreateReturnsDocumentReference(): void
    {
        $this->seedDemoBaseConfig(DocumentType::INVOICE->value);

        $orderId = $this->createDraftOrder();

        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_action/order/document-v2/create',
            [
                'orderId' => $orderId,
                'documentType' => DocumentType::INVOICE->value,
                'formats' => [
                    DocumentFormat::HTML->value,
                ],
                'documentNumber' => '1001-' . Uuid::randomHex(),
                'documentDate' => self::DOCUMENT_DATE,
            ],
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsString($payload['documentId'] ?? null);
        static::assertTrue(Uuid::isValid($payload['documentId']));
        static::assertIsString($payload['deepLinkCode'] ?? null);
        static::assertNotSame('', $payload['deepLinkCode']);
        static::assertSame([DocumentFormat::HTML->value], $payload['formats'] ?? null);

        $files = $this->loadDocumentFiles($payload['documentId']);
        static::assertCount(1, $files);

        $file = $files->first();
        static::assertInstanceOf(DocumentFileEntity::class, $file);
        static::assertSame(DocumentFormat::HTML->value, $file->getDocumentFormat());
    }

    public function testCreateMapsTheDeliveryDateForADeliveryNote(): void
    {
        $this->seedDemoBaseConfig(DocumentType::DELIVERY_NOTE->value);

        $orderId = $this->createDraftOrder();

        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_action/order/document-v2/create',
            [
                'orderId' => $orderId,
                'documentType' => DocumentType::DELIVERY_NOTE->value,
                'formats' => [
                    DocumentFormat::HTML->value,
                ],
                'documentNumber' => '3001-' . Uuid::randomHex(),
                'documentDate' => self::DOCUMENT_DATE,
                'deliveryDate' => '2026-07-30T00:00:00+00:00',
            ],
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testCreateMapsTheReferencedDocumentIdForACancellationInvoice(): void
    {
        $this->seedDemoBaseConfig(DocumentType::INVOICE->value);
        $this->seedDemoBaseConfig(DocumentType::CANCELLATION_INVOICE->value);

        $orderId = $this->createDraftOrder();
        $invoiceId = $this->seedReferenceInvoice($orderId);

        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_action/order/document-v2/create',
            [
                'orderId' => $orderId,
                'documentType' => DocumentType::CANCELLATION_INVOICE->value,
                'formats' => [
                    DocumentFormat::HTML->value,
                ],
                'documentNumber' => '2001-' . Uuid::randomHex(),
                'documentDate' => self::DOCUMENT_DATE,
                'referencedDocumentId' => $invoiceId,
            ],
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsString($payload['documentId'] ?? null);

        $document = static::getContainer()->get('document.repository')
            ->search(new Criteria([$payload['documentId']]), $this->context)->getEntities()->first();

        static::assertInstanceOf(DocumentEntity::class, $document);
        static::assertSame($invoiceId, $document->getReferencedDocumentId());
    }

    public function testUploadStoresDocumentFileAndDownloadReturnsIt(): void
    {
        $orderId = $this->createDraftOrder();
        $orderVersionId = $this->orderRepository->createVersion($orderId, $this->context, 'DRAFT');

        $content = 'uploaded invoice';

        $this->getBrowser()->request(
            'POST',
            '/api/_action/order/document-v2/upload?' . http_build_query([
                'documentDate' => self::DOCUMENT_DATE,
                'documentNumber' => '1002-' . Uuid::randomHex(),
                'documentType' => DocumentType::INVOICE->value,
                'extension' => DocumentFormat::PDF->value,
                'fileName' => 'uploaded-invoice',
                'format' => DocumentFormat::PDF->value,
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
            ], '', '&', \PHP_QUERY_RFC3986),
            [],
            [],
            [
                'HTTP_CONTENT_LENGTH' => \strlen($content),
                'HTTP_CONTENT_TYPE' => DocumentFormat::PDF->mimeType(),
            ],
            $content,
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsString($payload['documentId'] ?? null);
        static::assertTrue(Uuid::isValid($payload['documentId']));
        static::assertIsString($payload['deepLinkCode'] ?? null);
        static::assertNotSame('', $payload['deepLinkCode']);
        static::assertSame([DocumentFormat::PDF->value], $payload['formats'] ?? null);

        $files = $this->loadDocumentFiles($payload['documentId']);
        static::assertCount(1, $files);

        $file = $files->first();
        static::assertInstanceOf(DocumentFileEntity::class, $file);
        static::assertSame(DocumentFormat::PDF->value, $file->getDocumentFormat());

        $this->getBrowser()->request(
            'GET',
            \sprintf(
                '/api/_action/order/document-v2/%s/download/%s',
                $payload['documentId'],
                DocumentFormat::PDF->value,
            ),
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame($content, $response->getContent());
        static::assertSame(DocumentFormat::PDF->mimeType(), $response->headers->get('content-type'));
        static::assertStringStartsWith('attachment;', (string) $response->headers->get('content-disposition'));
        static::assertStringContainsString('uploaded-invoice.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function testDownloadFallsBackToLegacyDocumentFile(): void
    {
        $this->seedDemoBaseConfig(DocumentType::INVOICE->value);
        $orderId = $this->createDraftOrder();
        $documentNumber = 'legacy-' . Uuid::randomHex();
        $config = $this->getDemoInvoiceLegacyConfig();
        $config['documentNumber'] = $documentNumber;

        $document = static::getContainer()->get(LegacyDocumentGenerator::class)->generate(
            InvoiceRenderer::TYPE,
            [$orderId => new DocumentGenerateOperation($orderId, PdfRenderer::FILE_EXTENSION, $config)],
            $this->context,
        )->getSuccess()->first();

        static::assertNotNull($document);

        $this->getBrowser()->request(
            'GET',
            \sprintf(
                '/api/_action/order/document-v2/%s/download/%s',
                $document->getId(),
                DocumentFormat::PDF->value,
            ),
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame(PdfRenderer::FILE_CONTENT_TYPE, $response->headers->get('content-type'));
        static::assertStringStartsWith('attachment;', (string) $response->headers->get('content-disposition'));
        static::assertStringContainsString($documentNumber . '.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function testDownloadArchiveFallsBackToLegacyDocumentFile(): void
    {
        $this->seedDemoBaseConfig(DocumentType::INVOICE->value);
        $orderId = $this->createDraftOrder();
        $documentNumber = 'legacy-archive-' . Uuid::randomHex();
        $config = $this->getDemoInvoiceLegacyConfig();
        $config['documentNumber'] = $documentNumber;

        $document = static::getContainer()->get(LegacyDocumentGenerator::class)->generate(
            InvoiceRenderer::TYPE,
            [$orderId => new DocumentGenerateOperation($orderId, PdfRenderer::FILE_EXTENSION, $config)],
            $this->context,
        )->getSuccess()->first();

        static::assertNotNull($document);

        $this->getBrowser()->request(
            'POST',
            '/api/_action/order/document-v2/download-archive',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['documentIds' => [$document->getId()]], \JSON_THROW_ON_ERROR),
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame('application/zip', $response->headers->get('content-type'));

        $tempFile = tempnam(sys_get_temp_dir(), 'document-v2-test-');
        static::assertIsString($tempFile);
        (new Filesystem())->dumpFile($tempFile, (string) $response->getContent());

        $zip = new \ZipArchive();
        static::assertTrue($zip->open($tempFile));
        static::assertSame('invoice_' . $documentNumber . '.pdf', $zip->getNameIndex(0));
        $zip->close();

        (new Filesystem())->remove($tempFile);
    }

    public function testUploadRejectsNonExistentMediaId(): void
    {
        $orderId = $this->createDraftOrder();
        $orderVersionId = $this->orderRepository->createVersion($orderId, $this->context, 'DRAFT');

        $browser = $this->getBrowser(true, [], [
            'document:create',
            'document:read',
            'document_file:create',
            'document_file:read',
        ]);

        $browser->jsonRequest(
            'POST',
            '/api/_action/order/document-v2/upload',
            [
                'documentType' => DocumentType::INVOICE->value,
                'format' => DocumentFormat::PDF->value,
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
                'mediaId' => Uuid::randomHex(),
                'documentNumber' => '1004-' . Uuid::randomHex(),
            ],
        );

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), (string) $response->getContent());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(DocumentV2Exception::MEDIA_NOT_FOUND, $payload['errors'][0]['code'] ?? null);
    }

    public function testUploadAcceptsMediaFromTheDocumentFolder(): void
    {
        $orderId = $this->createDraftOrder();
        $orderVersionId = $this->orderRepository->createVersion($orderId, $this->context, 'DRAFT');

        $content = 'referenced invoice';
        $mediaId = $this->createDocumentMedia($content);

        $browser = $this->getBrowser(true, [], [
            'document:create',
            'document:read',
            'document_file:create',
            'document_file:read',
        ]);

        $browser->jsonRequest(
            'POST',
            '/api/_action/order/document-v2/upload',
            [
                'documentType' => DocumentType::INVOICE->value,
                'format' => DocumentFormat::PDF->value,
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
                'mediaId' => $mediaId,
                'documentNumber' => '1005-' . Uuid::randomHex(),
            ],
        );

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $browser->request(
            'GET',
            \sprintf('/api/_action/order/document-v2/%s/download/%s', $payload['documentId'], DocumentFormat::PDF->value),
        );

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame($content, $response->getContent());
    }

    public function testUploadAcceptsPublicLibraryMedia(): void
    {
        $orderId = $this->createDraftOrder();
        $orderVersionId = $this->orderRepository->createVersion($orderId, $this->context, 'DRAFT');

        $content = 'library invoice';
        $mediaId = $this->createPublicLibraryMedia($content);

        $browser = $this->getBrowser(true, [], [
            'document:create',
            'document:read',
            'document_file:create',
            'document_file:read',
        ]);

        $browser->jsonRequest(
            'POST',
            '/api/_action/order/document-v2/upload',
            [
                'documentType' => DocumentType::INVOICE->value,
                'format' => DocumentFormat::PDF->value,
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
                'mediaId' => $mediaId,
                'documentNumber' => '1008-' . Uuid::randomHex(),
            ],
        );

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $browser->request(
            'GET',
            \sprintf('/api/_action/order/document-v2/%s/download/%s', $payload['documentId'], DocumentFormat::PDF->value),
        );

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame($content, $response->getContent());
    }

    public function testUploadRejectsPrivateMediaOutsideTheDocumentFolder(): void
    {
        $orderId = $this->createDraftOrder();
        $orderVersionId = $this->orderRepository->createVersion($orderId, $this->context, 'DRAFT');

        $mediaId = $this->createForeignMedia('foreign content');

        $browser = $this->getBrowser(true, [], [
            'document:create',
            'document:read',
            'document_file:create',
            'document_file:read',
        ]);

        $browser->jsonRequest(
            'POST',
            '/api/_action/order/document-v2/upload',
            [
                'documentType' => DocumentType::INVOICE->value,
                'format' => DocumentFormat::PDF->value,
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
                'mediaId' => $mediaId,
                'documentNumber' => '1006-' . Uuid::randomHex(),
            ],
        );

        $this->assertMediaNotAllowed($browser->getResponse());
    }

    public function testDownloadRejectsDocumentFileLinkedToForeignMediaThroughTheApi(): void
    {
        $documentId = $this->createDocumentThroughApi(documentFileMediaId: $this->createForeignMedia('foreign content'));

        $this->getBrowser()->request(
            'GET',
            \sprintf('/api/_action/order/document-v2/%s/download/%s', $documentId, DocumentFormat::PDF->value),
        );

        $this->assertMediaNotAllowed($this->getBrowser()->getResponse());
    }

    public function testDownloadRejectsLegacyMediaLinkedToForeignMediaThroughTheApi(): void
    {
        $documentId = $this->createDocumentThroughApi(legacyMediaId: $this->createForeignMedia('foreign content'));

        $this->getBrowser()->request(
            'GET',
            \sprintf('/api/_action/order/document-v2/%s/download/%s', $documentId, DocumentFormat::PDF->value),
        );

        $this->assertMediaNotAllowed($this->getBrowser()->getResponse());
    }

    public function testDownloadArchiveRejectsForeignMedia(): void
    {
        $documentId = $this->createDocumentThroughApi(documentFileMediaId: $this->createForeignMedia('foreign content'));

        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_action/order/document-v2/download-archive',
            ['documentIds' => [$documentId]],
        );

        $this->assertMediaNotAllowed($this->getBrowser()->getResponse());
    }

    public function testDownloadServesDocumentLinkedToPublicLibraryMedia(): void
    {
        $content = 'library content';
        $documentId = $this->createDocumentThroughApi(documentFileMediaId: $this->createPublicLibraryMedia($content));

        $this->getBrowser()->request(
            'GET',
            \sprintf('/api/_action/order/document-v2/%s/download/%s', $documentId, DocumentFormat::PDF->value),
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame($content, $response->getContent());
    }

    public function testDownloadStillWorksWithoutMediaReadPrivilege(): void
    {
        $orderId = $this->createDraftOrder();
        $orderVersionId = $this->orderRepository->createVersion($orderId, $this->context, 'DRAFT');

        $content = 'support agent invoice';

        $this->getBrowser()->request(
            'POST',
            '/api/_action/order/document-v2/upload?' . http_build_query([
                'documentNumber' => '1007-' . Uuid::randomHex(),
                'documentType' => DocumentType::INVOICE->value,
                'extension' => DocumentFormat::PDF->value,
                'fileName' => 'support-invoice',
                'format' => DocumentFormat::PDF->value,
                'orderId' => $orderId,
                'orderVersionId' => $orderVersionId,
            ], '', '&', \PHP_QUERY_RFC3986),
            [],
            [],
            [
                'HTTP_CONTENT_LENGTH' => \strlen($content),
                'HTTP_CONTENT_TYPE' => DocumentFormat::PDF->mimeType(),
            ],
            $content,
        );

        $payload = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsString($payload['documentId'] ?? null);

        $browser = $this->getBrowser(true, [], [
            'document:read',
            'document_file:read',
        ]);

        $browser->request(
            'GET',
            \sprintf('/api/_action/order/document-v2/%s/download/%s', $payload['documentId'], DocumentFormat::PDF->value),
        );

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame($content, $response->getContent());
    }

    public function testNumberRangePatternCollisionsListsOtherNumberRangesOfTheSameDocumentType(): void
    {
        $numberRangeTypeId = $this->getNumberRangeTypeId('document_invoice');
        $firstId = $this->createNumberRange($numberRangeTypeId, 'Invoices shop A', 'COLLIDE-{n}');
        $secondId = $this->createNumberRange($numberRangeTypeId, 'Invoices shop B', 'COLLIDE-{n}');
        $this->createNumberRange($numberRangeTypeId, 'Invoices shop C', 'DISTINCT-{n}');

        $collisions = $this->requestNumberRangePatternCollisions([
            'numberRangeTypeId' => $numberRangeTypeId,
            'pattern' => 'COLLIDE-{n}',
            'numberRangeId' => $firstId,
        ]);

        static::assertSame([['id' => $secondId, 'name' => 'Invoices shop B']], $collisions);
    }

    public function testNumberRangePatternCollisionsWithoutNumberRangeIdIncludesEveryMatch(): void
    {
        $numberRangeTypeId = $this->getNumberRangeTypeId('document_invoice');
        $firstId = $this->createNumberRange($numberRangeTypeId, 'Invoices shop A', 'NEW-{n}');
        $secondId = $this->createNumberRange($numberRangeTypeId, 'Invoices shop B', 'NEW-{n}');

        $collisions = $this->requestNumberRangePatternCollisions([
            'numberRangeTypeId' => $numberRangeTypeId,
            'pattern' => 'NEW-{n}',
        ]);

        $ids = array_column($collisions, 'id');
        sort($ids);
        $expected = [$firstId, $secondId];
        sort($expected);

        static::assertSame($expected, $ids);
    }

    public function testNumberRangePatternCollisionsIgnoresOtherDocumentTypes(): void
    {
        $invoiceNumberRangeTypeId = $this->getNumberRangeTypeId('document_invoice');
        $creditNoteNumberRangeTypeId = $this->getNumberRangeTypeId('document_credit_note');
        $invoiceId = $this->createNumberRange($invoiceNumberRangeTypeId, 'Invoices', 'SHARED-{n}');
        $this->createNumberRange($creditNoteNumberRangeTypeId, 'Credit notes', 'SHARED-{n}');

        $collisions = $this->requestNumberRangePatternCollisions([
            'numberRangeTypeId' => $invoiceNumberRangeTypeId,
            'pattern' => 'SHARED-{n}',
            'numberRangeId' => $invoiceId,
        ]);

        static::assertSame([], $collisions);
    }

    public function testNumberRangePatternCollisionsIgnoresNonDocumentNumberRangeTypes(): void
    {
        $numberRangeTypeId = $this->getNumberRangeTypeId('customer');
        $firstId = $this->createNumberRange($numberRangeTypeId, 'Customers shop A', 'CUST-{n}');
        $this->createNumberRange($numberRangeTypeId, 'Customers shop B', 'CUST-{n}');

        $collisions = $this->requestNumberRangePatternCollisions([
            'numberRangeTypeId' => $numberRangeTypeId,
            'pattern' => 'CUST-{n}',
            'numberRangeId' => $firstId,
        ]);

        static::assertSame([], $collisions);
    }

    public function testNumberRangePatternCollisionsRequiresTypeIdAndPattern(): void
    {
        $browser = $this->getBrowser();

        $browser->jsonRequest('GET', '/api/_action/order/document-v2/number-range-pattern-collisions?pattern=' . rawurlencode('{n}'));
        static::assertSame(400, $browser->getResponse()->getStatusCode());

        $browser->jsonRequest('GET', '/api/_action/order/document-v2/number-range-pattern-collisions?numberRangeTypeId=' . $this->getNumberRangeTypeId('document_invoice'));
        static::assertSame(400, $browser->getResponse()->getStatusCode());

        $browser->jsonRequest('GET', '/api/_action/order/document-v2/number-range-pattern-collisions?numberRangeTypeId=not-a-uuid&pattern=' . rawurlencode('{n}'));
        static::assertSame(400, $browser->getResponse()->getStatusCode());
    }

    private function assertMediaNotAllowed(Response $response): void
    {
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(DocumentV2Exception::DOCUMENT_MEDIA_NOT_ALLOWED, $payload['errors'][0]['code'] ?? null);
    }

    private function createDocumentThroughApi(?string $documentFileMediaId = null, ?string $legacyMediaId = null): string
    {
        $orderId = $this->createDraftOrder();
        $orderVersionId = $this->orderRepository->createVersion($orderId, $this->context, 'DRAFT');

        $documentTypeId = static::getContainer()->get('document_type.repository')
            ->searchIds(
                (new Criteria())->addFilter(new EqualsFilter('technicalName', DocumentType::INVOICE->value)),
                $this->context,
            )
            ->firstId();

        $documentId = Uuid::randomHex();

        $this->getBrowser()->jsonRequest('POST', '/api/document', array_filter([
            'id' => $documentId,
            'typeName' => DocumentType::INVOICE->value,
            'documentTypeId' => $documentTypeId,
            'orderId' => $orderId,
            'orderVersionId' => $orderVersionId,
            'documentMediaFileId' => $legacyMediaId,
            'config' => ['documentNumber' => 'api-' . Uuid::randomHex()],
            'deepLinkCode' => Uuid::randomHex(),
            'static' => true,
        ]));

        static::assertSame(
            Response::HTTP_NO_CONTENT,
            $this->getBrowser()->getResponse()->getStatusCode(),
            (string) $this->getBrowser()->getResponse()->getContent(),
        );

        if ($documentFileMediaId !== null) {
            $this->getBrowser()->jsonRequest('POST', '/api/document-file', [
                'id' => Uuid::randomHex(),
                'documentId' => $documentId,
                'mediaId' => $documentFileMediaId,
                'documentFormat' => DocumentFormat::PDF->value,
            ]);

            static::assertSame(
                Response::HTTP_NO_CONTENT,
                $this->getBrowser()->getResponse()->getStatusCode(),
                (string) $this->getBrowser()->getResponse()->getContent(),
            );
        }

        return $documentId;
    }

    private function createDocumentMedia(string $content): string
    {
        return static::getContainer()->get(MediaService::class)->saveFile(
            $content,
            DocumentFormat::PDF->value,
            DocumentFormat::PDF->mimeType(),
            'referenced-invoice-' . Uuid::randomHex(),
            $this->context,
            DocumentPersister::MEDIA_FOLDER,
        );
    }

    private function createForeignMedia(string $content): string
    {
        return static::getContainer()->get(MediaService::class)->saveFile(
            $content,
            DocumentFormat::PDF->value,
            DocumentFormat::PDF->mimeType(),
            'foreign-' . Uuid::randomHex(),
            $this->context,
        );
    }

    private function createPublicLibraryMedia(string $content): string
    {
        return static::getContainer()->get(MediaService::class)->saveFile(
            $content,
            DocumentFormat::PDF->value,
            DocumentFormat::PDF->mimeType(),
            'library-' . Uuid::randomHex(),
            $this->context,
            private: false,
        );
    }

    private function createDraftOrder(): string
    {
        $cart = $this->generateDemoCartWithTaxes([19, 7]);
        $cart = $this->applyTenPercentPromotion($cart);
        $orderId = $this->persistCart($cart);
        $this->enrichOrderForRendering($orderId);

        return $orderId;
    }

    private function loadDocumentFiles(string $documentId): DocumentFileCollection
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('documentId', $documentId));

        return $this->documentFileRepository->search($criteria, $this->context)->getEntities();
    }

    /**
     * @param array<string, string> $query
     *
     * @return list<array{id: string, name: string}>
     */
    private function requestNumberRangePatternCollisions(array $query): array
    {
        $browser = $this->getBrowser();
        $browser->jsonRequest('GET', '/api/_action/order/document-v2/number-range-pattern-collisions?' . http_build_query($query));

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($content);
        static::assertArrayHasKey('collisions', $content);

        return $content['collisions'];
    }

    private function createNumberRange(string $numberRangeTypeId, string $name, string $pattern): string
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('number_range.repository')->create([[
            'id' => $id,
            'typeId' => $numberRangeTypeId,
            'name' => $name,
            'pattern' => $pattern,
            'start' => 1,
            'global' => false,
        ]], $this->context);

        return $id;
    }

    private function getNumberRangeTypeId(string $technicalName): string
    {
        $numberRangeTypeId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `number_range_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $technicalName]
        );

        static::assertIsString($numberRangeTypeId);

        return $numberRangeTypeId;
    }
}
