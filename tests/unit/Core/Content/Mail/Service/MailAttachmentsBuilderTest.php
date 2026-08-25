<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailAttachmentsBuilder::class)]
class MailAttachmentsBuilderTest extends TestCase
{
    private MediaService&Stub $mediaService;

    /**
     * @var EntityRepository<MediaCollection>&Stub
     */
    private EntityRepository&Stub $mediaRepository;

    private DocumentGenerator&Stub $documentGenerator;

    private Connection&Stub $connection;

    /**
     * @var EntityRepository<DocumentCollection>&Stub
     */
    private EntityRepository&Stub $documentRepository;

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->mediaService = static::createStub(MediaService::class);
        $this->mediaRepository = static::createStub(EntityRepository::class);
        $this->documentGenerator = static::createStub(DocumentGenerator::class);
        $this->connection = static::createStub(Connection::class);
        $this->documentRepository = static::createStub(EntityRepository::class);
        $this->logger = new NullLogger();
    }

    public function testBuildTemplateMediaAttachments(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $extension = new MailSendSubscriberConfig(false);

        $mediaA = new MailTemplateMediaEntity();
        $mediaA->setId(Uuid::randomHex());
        $mediaA->setMedia(new MediaEntity());
        $mediaA->setLanguageId($context->getLanguageId());
        $mediaB = new MailTemplateMediaEntity();
        $mediaB->setId(Uuid::randomHex());
        $mediaC = new MailTemplateMediaEntity();
        $mediaC->setId(Uuid::randomHex());
        $mediaC->setMedia(new MediaEntity());
        $mediaC->setLanguageId($context->getLanguageId());

        $mailTemplate->setMedia(new MailTemplateMediaCollection([$mediaA, $mediaB, $mediaC]));

        $mediaService = $this->createMock(MediaService::class);
        $mediaService
            ->expects($this->exactly(2))
            ->method('getAttachment')
            ->willReturnOnConsecutiveCalls(
                [
                    'content' => 'foo',
                    'fileName' => 'foo',
                    'mimeType' => 'foo',
                ],
                [
                    'content' => 'bar',
                    'fileName' => 'bar',
                    'mimeType' => 'bar',
                ]
            );
        $this->mediaService = $mediaService;

        $attachments = $this->createBuilder()->buildAttachments($context, $mailTemplate, $extension, [], Uuid::randomHex());

        static::assertSame(
            [
                [
                    'content' => 'foo',
                    'fileName' => 'foo',
                    'mimeType' => 'foo',
                ],
                [
                    'content' => 'bar',
                    'fileName' => 'bar',
                    'mimeType' => 'bar',
                ],
            ],
            $attachments
        );
    }

    public function testBuildTemplateDocumentAttachments(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $idA = Uuid::randomHex();
        $idB = Uuid::randomHex();
        $idC = Uuid::randomHex();
        $idD = Uuid::randomHex();
        $idE = Uuid::randomHex();
        $idF = Uuid::randomHex();
        $extension = new MailSendSubscriberConfig(false, [$idA, $idB], [$idE, $idF]);
        $eventConfig = ['documentTypeIds' => [$idA, $idB]];
        $orderId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                static::anything(),
                ['orderId' => Uuid::fromHexToBytes($orderId), 'documentTypeIds' => Uuid::fromHexToBytesList($eventConfig['documentTypeIds'])],
                ['documentTypeIds' => ArrayParameterType::BINARY]
            )
            ->willReturn([
                ['doc_type' => 'foo', 'doc_id' => '1'],
                ['doc_type' => 'bar', 'doc_id' => '2'],
                ['doc_type' => 'foo', 'doc_id' => '3'],
                ['doc_type' => 'foo', 'doc_id' => $idC],
                ['doc_type' => 'bar', 'doc_id' => $idD],
            ]);
        $this->connection = $connection;

        $document = new RenderedDocument();
        $document->setContent('');
        $documentGenerator = $this->createMock(DocumentGenerator::class);
        $documentGenerator
            ->expects($this->exactly(4))
            ->method('readDocument')
            ->willReturn($document);
        $this->documentGenerator = $documentGenerator;

        $criteria = new Criteria($extension->getMediaIds());
        $criteria->setTitle('send-mail::load-media');
        $entities = array_map(static function (string $id): MediaEntity {
            $media = new MediaEntity();
            $media->setId($id);

            return $media;
        }, $extension->getMediaIds());

        $mediaRepository = $this->createMock(EntityRepository::class);
        $mediaRepository
            ->expects($this->once())
            ->method('search')
            ->with($criteria, $context)
            ->willReturn(new EntitySearchResult('media', 2, new MediaCollection($entities), null, $criteria, $context));
        $this->mediaRepository = $mediaRepository;

        $mediaService = $this->createMock(MediaService::class);
        $mediaService
            ->expects($this->exactly(2))
            ->method('getAttachment')
            ->willReturnOnConsecutiveCalls(
                [
                    'content' => '',
                    'fileName' => '',
                    'mimeType' => 'application/pdf',
                    'id' => $idE,
                ],
                [
                    'content' => '',
                    'fileName' => '',
                    'mimeType' => 'application/pdf',
                    'id' => $idF,
                ]
            );
        $this->mediaService = $mediaService;

        $attachments = $this->createBuilder()->buildAttachments($context, $mailTemplate, $extension, $eventConfig, $orderId);

        static::assertEquals(
            [
                [
                    'content' => '',
                    'fileName' => '',
                    'mimeType' => 'application/pdf',
                    'id' => $idA,
                    'documentId' => $idA,
                ],
                [
                    'content' => '',
                    'fileName' => '',
                    'mimeType' => 'application/pdf',
                    'id' => $idB,
                    'documentId' => $idB,
                ],
                [
                    'content' => '',
                    'fileName' => '',
                    'mimeType' => 'application/pdf',
                    'id' => $idC,
                    'documentId' => $idC,
                ],
                [
                    'content' => '',
                    'fileName' => '',
                    'mimeType' => 'application/pdf',
                    'id' => $idD,
                    'documentId' => $idD,
                ],
                [
                    'content' => '',
                    'fileName' => '',
                    'mimeType' => 'application/pdf',
                    'id' => $idE,
                ],
                [
                    'content' => '',
                    'fileName' => '',
                    'mimeType' => 'application/pdf',
                    'id' => $idF,
                ],
            ],
            $attachments
        );
    }

    public function testBuildTemplateDocumentAttachmentsForXmlDocument(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $xmlDocId = Uuid::randomHex();
        $extension = new MailSendSubscriberConfig(false, [$xmlDocId]);

        $document = new RenderedDocument();
        $document->setContent('<?xml version="1.0"?>');
        $document->setName('invoice.xml');
        $document->setContentType('application/xml');

        $documentGenerator = $this->createMock(DocumentGenerator::class);
        $documentGenerator
            ->expects($this->once())
            ->method('readDocument')
            ->with($xmlDocId, $context, '', null)
            ->willReturn($document);
        $this->documentGenerator = $documentGenerator;

        // A document generated before document_v2 has no document_files, so buildDocumentAttachments()
        // falls back to the legacy DocumentGenerator::readDocument() for it.
        $documentEntity = new DocumentEntity();
        $documentEntity->setId($xmlDocId);

        $criteria = (new Criteria([$xmlDocId]))->addAssociation('documentFiles.media');
        $criteria->setTitle('send-mail::load-document-files');

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository
            ->expects($this->once())
            ->method('search')
            ->with($criteria, $context)
            ->willReturn(new EntitySearchResult('document', 1, new DocumentCollection([$documentEntity]), null, $criteria, $context));
        $this->documentRepository = $documentRepository;

        $mediaRepository = $this->createMock(EntityRepository::class);
        $mediaRepository
            ->expects($this->never())
            ->method('search');
        $this->mediaRepository = $mediaRepository;

        $attachments = $this->createBuilder()->buildAttachments($context, $mailTemplate, $extension, [], null);

        static::assertCount(1, $attachments);
        $attachment = $attachments[0];
        static::assertArrayHasKey('id', $attachment);
        static::assertArrayHasKey('documentId', $attachment);
        static::assertSame($xmlDocId, $attachment['id']);
        static::assertSame($xmlDocId, $attachment['documentId']);
        static::assertSame('<?xml version="1.0"?>', $attachment['content']);
        static::assertSame('invoice.xml', $attachment['fileName']);
        static::assertSame('application/xml', $attachment['mimeType']);
    }

    public function testBuildTemplateDocumentAttachmentsForPreResolvedDocumentV2Id(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $documentId = Uuid::randomHex();
        $extension = new MailSendSubscriberConfig(false, [$documentId]);

        $pdfMedia = new MediaEntity();
        $pdfMedia->setId(Uuid::randomHex());
        $pdfMedia->setFileName('invoice');
        $pdfMedia->setFileExtension('pdf');
        $pdfMedia->setMimeType('application/pdf');

        $pdfFile = new DocumentFileEntity();
        $pdfFile->setId(Uuid::randomHex());
        $pdfFile->setDocumentId($documentId);
        $pdfFile->setMediaId($pdfMedia->getId());
        $pdfFile->setDocumentFormat('pdf');
        $pdfFile->setMedia($pdfMedia);

        $htmlFile = $this->createHtmlDocumentFile($documentId);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDocumentFiles(new DocumentFileCollection([$pdfFile, $htmlFile]));

        $criteria = (new Criteria([$documentId]))->addAssociation('documentFiles.media');
        $criteria->setTitle('send-mail::load-document-files');

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository
            ->expects($this->once())
            ->method('search')
            ->with($criteria, $context)
            ->willReturn(new EntitySearchResult('document', 1, new DocumentCollection([$document]), null, $criteria, $context));
        $this->documentRepository = $documentRepository;

        $mediaService = $this->createMock(MediaService::class);
        $mediaService
            ->expects($this->once())
            ->method('loadFile')
            ->with($pdfMedia->getId(), $context)
            ->willReturn('pdf-content');
        $this->mediaService = $mediaService;

        $documentGenerator = $this->createMock(DocumentGenerator::class);
        $documentGenerator->expects($this->never())->method('readDocument');
        $this->documentGenerator = $documentGenerator;

        $attachments = $this->createBuilder()->buildAttachments($context, $mailTemplate, $extension, [], null);

        static::assertCount(1, $attachments);
        static::assertArrayHasKey('id', $attachments[0]);
        static::assertArrayHasKey('documentId', $attachments[0]);
        static::assertSame($pdfFile->getId(), $attachments[0]['id']);
        static::assertSame($documentId, $attachments[0]['documentId']);
        static::assertSame('pdf-content', $attachments[0]['content']);
    }

    public function testBuildTemplateDocumentAttachmentsWithRequestedFileFormats(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $documentId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $extension = new MailSendSubscriberConfig(false);
        $eventConfig = [
            'documentType' => 'invoice',
            'fileFormats' => ['pdf'],
        ];

        $pdfMedia = new MediaEntity();
        $pdfMedia->setId(Uuid::randomHex());
        $pdfMedia->setFileName('invoice');
        $pdfMedia->setFileExtension('pdf');
        $pdfMedia->setMimeType('application/pdf');

        $pdfFile = new DocumentFileEntity();
        $pdfFile->setId(Uuid::randomHex());
        $pdfFile->setDocumentId($documentId);
        $pdfFile->setMediaId($pdfMedia->getId());
        $pdfFile->setDocumentFormat('pdf');
        $pdfFile->setMedia($pdfMedia);

        $xmlMedia = new MediaEntity();
        $xmlMedia->setId(Uuid::randomHex());
        $xmlMedia->setFileName('invoice');
        $xmlMedia->setFileExtension('xml');
        $xmlMedia->setMimeType('application/xml');

        $xmlFile = new DocumentFileEntity();
        $xmlFile->setId(Uuid::randomHex());
        $xmlFile->setDocumentId($documentId);
        $xmlFile->setMediaId($xmlMedia->getId());
        $xmlFile->setDocumentFormat('zugferd_xml');
        $xmlFile->setMedia($xmlMedia);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDocumentFiles(new DocumentFileCollection([$pdfFile, $xmlFile]));

        $criteria = (new Criteria([$documentId]))->addAssociation('documentFiles.media');
        $criteria->setTitle('send-mail::load-document-files');

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([$documentId], new Criteria(), $context));
        $documentRepository
            ->expects($this->once())
            ->method('search')
            ->with($criteria, $context)
            ->willReturn(new EntitySearchResult('document', 1, new DocumentCollection([$document]), null, $criteria, $context));
        $this->documentRepository = $documentRepository;

        $mediaService = $this->createMock(MediaService::class);
        $mediaService
            ->expects($this->once())
            ->method('loadFile')
            ->with($pdfMedia->getId(), $context)
            ->willReturn('pdf-content');
        $this->mediaService = $mediaService;

        $attachments = $this->createBuilder()->buildAttachments($context, $mailTemplate, $extension, $eventConfig, $orderId);

        static::assertCount(1, $attachments);
        static::assertArrayHasKey('id', $attachments[0]);
        static::assertArrayHasKey('documentId', $attachments[0]);
        static::assertSame($pdfFile->getId(), $attachments[0]['id']);
        static::assertSame($documentId, $attachments[0]['documentId']);
        static::assertSame('pdf-content', $attachments[0]['content']);
        static::assertSame('invoice.pdf', $attachments[0]['fileName']);
        static::assertSame('application/pdf', $attachments[0]['mimeType']);
    }

    public function testBuildTemplateDocumentAttachmentsLogsOriginalSelectionAndGeneratedHtml(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $documentId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $extension = new MailSendSubscriberConfig(false);
        $eventConfig = [
            'documentType' => 'invoice',
            'fileFormats' => ['pdf', 'html'],
        ];

        $htmlFile = $this->createHtmlDocumentFile($documentId);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDocumentFiles(new DocumentFileCollection([$htmlFile]));

        $criteria = (new Criteria([$documentId]))->addAssociation('documentFiles.media');
        $criteria->setTitle('send-mail::load-document-files');

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([$documentId], new Criteria(), $context));
        $documentRepository
            ->expects($this->once())
            ->method('search')
            ->with($criteria, $context)
            ->willReturn(new EntitySearchResult('document', 1, new DocumentCollection([$document]), null, $criteria, $context));
        $this->documentRepository = $documentRepository;

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('loadFile');
        $this->mediaService = $mediaService;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                static::anything(),
                static::callback(static function (array $logContext) use ($documentId) {
                    static::assertSame($documentId, $logContext['documentId']);
                    static::assertSame(['pdf', 'html'], $logContext['requestedFormats']);
                    static::assertSame(['html'], $logContext['availableFormats']);

                    return true;
                })
            );
        $this->logger = $logger;

        $attachments = $this->createBuilder()->buildAttachments($context, $mailTemplate, $extension, $eventConfig, $orderId);

        static::assertSame([], $attachments);
    }

    public function testBuildTemplateDocumentAttachmentsLogsWhenRequestedFormatIsUnavailable(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $documentId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $extension = new MailSendSubscriberConfig(false);
        $eventConfig = [
            'documentType' => 'invoice',
            'fileFormats' => ['pdf'],
        ];

        $xmlMedia = new MediaEntity();
        $xmlMedia->setId(Uuid::randomHex());
        $xmlMedia->setFileName('invoice');
        $xmlMedia->setFileExtension('xml');
        $xmlMedia->setMimeType('application/xml');

        $xmlFile = new DocumentFileEntity();
        $xmlFile->setId(Uuid::randomHex());
        $xmlFile->setDocumentId($documentId);
        $xmlFile->setMediaId($xmlMedia->getId());
        $xmlFile->setDocumentFormat('zugferd_xml');
        $xmlFile->setMedia($xmlMedia);

        $htmlFile = $this->createHtmlDocumentFile($documentId);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDocumentFiles(new DocumentFileCollection([$xmlFile, $htmlFile]));

        $criteria = (new Criteria([$documentId]))->addAssociation('documentFiles.media');
        $criteria->setTitle('send-mail::load-document-files');

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([$documentId], new Criteria(), $context));
        $documentRepository
            ->expects($this->once())
            ->method('search')
            ->with($criteria, $context)
            ->willReturn(new EntitySearchResult('document', 1, new DocumentCollection([$document]), null, $criteria, $context));
        $this->documentRepository = $documentRepository;

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('loadFile');
        $this->mediaService = $mediaService;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                static::anything(),
                static::callback(static function (array $logContext) use ($documentId) {
                    static::assertSame($documentId, $logContext['documentId']);
                    static::assertSame(['pdf'], $logContext['requestedFormats']);
                    static::assertSame(['zugferd_xml', 'html'], $logContext['availableFormats']);

                    return true;
                })
            );
        $this->logger = $logger;

        $attachments = $this->createBuilder()->buildAttachments($context, $mailTemplate, $extension, $eventConfig, $orderId);

        static::assertSame([], $attachments);
    }

    public function testBuildTemplateDocumentAttachmentsWithoutRequestedFileFormatsAttachesEveryFormatExceptHtml(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $documentId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $extension = new MailSendSubscriberConfig(false);
        $eventConfig = ['documentType' => 'invoice'];

        $pdfMedia = new MediaEntity();
        $pdfMedia->setId(Uuid::randomHex());
        $pdfMedia->setFileName('invoice');
        $pdfMedia->setFileExtension('pdf');
        $pdfMedia->setMimeType('application/pdf');

        $pdfFile = new DocumentFileEntity();
        $pdfFile->setId(Uuid::randomHex());
        $pdfFile->setDocumentId($documentId);
        $pdfFile->setMediaId($pdfMedia->getId());
        $pdfFile->setDocumentFormat('pdf');
        $pdfFile->setMedia($pdfMedia);

        $xmlMedia = new MediaEntity();
        $xmlMedia->setId(Uuid::randomHex());
        $xmlMedia->setFileName('invoice');
        $xmlMedia->setFileExtension('xml');
        $xmlMedia->setMimeType('application/xml');

        $xmlFile = new DocumentFileEntity();
        $xmlFile->setId(Uuid::randomHex());
        $xmlFile->setDocumentId($documentId);
        $xmlFile->setMediaId($xmlMedia->getId());
        $xmlFile->setDocumentFormat('zugferd_xml');
        $xmlFile->setMedia($xmlMedia);

        $htmlFile = $this->createHtmlDocumentFile($documentId);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDocumentFiles(new DocumentFileCollection([$pdfFile, $xmlFile, $htmlFile]));

        $criteria = (new Criteria([$documentId]))->addAssociation('documentFiles.media');
        $criteria->setTitle('send-mail::load-document-files');

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([$documentId], new Criteria(), $context));
        $documentRepository
            ->expects($this->once())
            ->method('search')
            ->with($criteria, $context)
            ->willReturn(new EntitySearchResult('document', 1, new DocumentCollection([$document]), null, $criteria, $context));
        $this->documentRepository = $documentRepository;

        $mediaService = $this->createMock(MediaService::class);
        $mediaService
            ->expects($this->exactly(2))
            ->method('loadFile')
            ->willReturnOnConsecutiveCalls('pdf-content', 'xml-content');
        $this->mediaService = $mediaService;

        $attachments = $this->createBuilder()->buildAttachments($context, $mailTemplate, $extension, $eventConfig, $orderId);

        static::assertCount(2, $attachments);
        static::assertArrayHasKey('id', $attachments[0]);
        static::assertArrayHasKey('id', $attachments[1]);
        static::assertSame($pdfFile->getId(), $attachments[0]['id']);
        static::assertSame('pdf-content', $attachments[0]['content']);
        static::assertSame($xmlFile->getId(), $attachments[1]['id']);
        static::assertSame('xml-content', $attachments[1]['content']);
    }

    public function testBuildTemplateDocumentAttachmentsWithHtmlOnlySelectionAttachesNothingWithoutWarning(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $documentId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $extension = new MailSendSubscriberConfig(false);
        $eventConfig = [
            'documentType' => 'invoice',
            'fileFormats' => ['html'],
        ];

        $pdfMedia = new MediaEntity();
        $pdfMedia->setId(Uuid::randomHex());
        $pdfMedia->setFileName('invoice');
        $pdfMedia->setFileExtension('pdf');
        $pdfMedia->setMimeType('application/pdf');

        $pdfFile = new DocumentFileEntity();
        $pdfFile->setId(Uuid::randomHex());
        $pdfFile->setDocumentId($documentId);
        $pdfFile->setMediaId($pdfMedia->getId());
        $pdfFile->setDocumentFormat('pdf');
        $pdfFile->setMedia($pdfMedia);

        $htmlFile = $this->createHtmlDocumentFile($documentId);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDocumentFiles(new DocumentFileCollection([$pdfFile, $htmlFile]));

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([$documentId], new Criteria(), $context));
        $documentRepository
            ->method('search')
            ->willReturn(new EntitySearchResult('document', 1, new DocumentCollection([$document]), null, new Criteria(), $context));
        $this->documentRepository = $documentRepository;

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->never())->method('loadFile');
        $this->mediaService = $mediaService;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');
        $this->logger = $logger;

        $attachments = $this->createBuilder()->buildAttachments($context, $mailTemplate, $extension, $eventConfig, $orderId);

        static::assertSame([], $attachments);
    }

    public function testBuildTemplateDocumentAttachmentsWithHtmlAndPdfSelectionAttachesOnlyPdf(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $documentId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $extension = new MailSendSubscriberConfig(false);
        $eventConfig = [
            'documentType' => 'invoice',
            'fileFormats' => ['pdf', 'html'],
        ];

        $pdfMedia = new MediaEntity();
        $pdfMedia->setId(Uuid::randomHex());
        $pdfMedia->setFileName('invoice');
        $pdfMedia->setFileExtension('pdf');
        $pdfMedia->setMimeType('application/pdf');

        $pdfFile = new DocumentFileEntity();
        $pdfFile->setId(Uuid::randomHex());
        $pdfFile->setDocumentId($documentId);
        $pdfFile->setMediaId($pdfMedia->getId());
        $pdfFile->setDocumentFormat('pdf');
        $pdfFile->setMedia($pdfMedia);

        $htmlFile = $this->createHtmlDocumentFile($documentId);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDocumentFiles(new DocumentFileCollection([$pdfFile, $htmlFile]));

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([$documentId], new Criteria(), $context));
        $documentRepository
            ->method('search')
            ->willReturn(new EntitySearchResult('document', 1, new DocumentCollection([$document]), null, new Criteria(), $context));
        $this->documentRepository = $documentRepository;

        $mediaService = $this->createMock(MediaService::class);
        $mediaService
            ->expects($this->once())
            ->method('loadFile')
            ->with($pdfMedia->getId(), $context)
            ->willReturn('pdf-content');
        $this->mediaService = $mediaService;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');
        $this->logger = $logger;

        $attachments = $this->createBuilder()->buildAttachments($context, $mailTemplate, $extension, $eventConfig, $orderId);

        static::assertCount(1, $attachments);
        static::assertArrayHasKey('id', $attachments[0]);
        static::assertSame($pdfFile->getId(), $attachments[0]['id']);
        static::assertSame('pdf-content', $attachments[0]['content']);
    }

    private function createHtmlDocumentFile(string $documentId): DocumentFileEntity
    {
        $htmlMedia = new MediaEntity();
        $htmlMedia->setId(Uuid::randomHex());
        $htmlMedia->setFileName('invoice');
        $htmlMedia->setFileExtension('html');
        $htmlMedia->setMimeType('text/html');

        $htmlFile = new DocumentFileEntity();
        $htmlFile->setId(Uuid::randomHex());
        $htmlFile->setDocumentId($documentId);
        $htmlFile->setMediaId($htmlMedia->getId());
        $htmlFile->setDocumentFormat('html');
        $htmlFile->setMedia($htmlMedia);

        return $htmlFile;
    }

    private function createBuilder(): MailAttachmentsBuilder
    {
        return new MailAttachmentsBuilder(
            $this->mediaService,
            $this->mediaRepository,
            $this->documentGenerator,
            $this->connection,
            $this->documentRepository,
            $this->logger
        );
    }
}
