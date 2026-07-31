<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentFileResolver;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;

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

    private DocumentFileResolver&Stub $documentFileResolver;

    protected function setUp(): void
    {
        $this->mediaService = static::createStub(MediaService::class);
        $this->mediaRepository = static::createStub(EntityRepository::class);
        $this->documentGenerator = static::createStub(DocumentGenerator::class);
        $this->connection = static::createStub(Connection::class);
        $this->documentFileResolver = static::createStub(DocumentFileResolver::class);
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

    #[DisabledFeatures(['DOCUMENT_GENERATION_REWORK'])]
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
                ],
                [
                    'content' => '',
                    'fileName' => '',
                    'mimeType' => 'application/pdf',
                    'id' => $idB,
                ],
                [
                    'content' => '',
                    'fileName' => '',
                    'mimeType' => 'application/pdf',
                    'id' => $idC,
                ],
                [
                    'content' => '',
                    'fileName' => '',
                    'mimeType' => 'application/pdf',
                    'id' => $idD,
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

    #[DisabledFeatures(['DOCUMENT_GENERATION_REWORK'])]
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

        $mediaRepository = $this->createMock(EntityRepository::class);
        $mediaRepository
            ->expects($this->never())
            ->method('search');
        $this->mediaRepository = $mediaRepository;

        $attachments = $this->createBuilder()->buildAttachments($context, $mailTemplate, $extension, [], null);

        static::assertCount(1, $attachments);
        $attachment = $attachments[0];
        static::assertArrayHasKey('id', $attachment);
        static::assertSame($xmlDocId, $attachment['id']);
        static::assertSame('<?xml version="1.0"?>', $attachment['content']);
        static::assertSame('invoice.xml', $attachment['fileName']);
        static::assertSame('application/xml', $attachment['mimeType']);
    }

    public function testBuildAttachmentsAttachesEveryFormatTheDocumentV2WasGeneratedIn(): void
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

        $htmlMedia = new MediaEntity();
        $htmlMedia->setId(Uuid::randomHex());
        $htmlMedia->setFileName('invoice');
        $htmlMedia->setFileExtension('html');
        $htmlMedia->setMimeType('text/html');

        $pdfDocumentFile = new DocumentFileEntity();
        $pdfDocumentFile->setId(Uuid::randomHex());
        $pdfDocumentFile->setDocumentFormat('pdf');
        $pdfDocumentFile->setMediaId($pdfMedia->getId());
        $pdfDocumentFile->setMedia($pdfMedia);

        $htmlDocumentFile = new DocumentFileEntity();
        $htmlDocumentFile->setId(Uuid::randomHex());
        $htmlDocumentFile->setDocumentFormat('html');
        $htmlDocumentFile->setMediaId($htmlMedia->getId());
        $htmlDocumentFile->setMedia($htmlMedia);

        $document = new DocumentEntity();
        $document->setId($documentId);
        $document->setDocumentFiles(new DocumentFileCollection([$pdfDocumentFile, $htmlDocumentFile]));

        $documentFileResolver = $this->createMock(DocumentFileResolver::class);
        $documentFileResolver->expects($this->once())
            ->method('loadDocument')
            ->with($documentId, $context)
            ->willReturn($document);
        $this->documentFileResolver = $documentFileResolver;

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->exactly(2))
            ->method('loadFile')
            ->willReturnCallback(static fn (string $mediaId): string => match ($mediaId) {
                $pdfMedia->getId() => 'pdf content',
                $htmlMedia->getId() => 'html content',
                default => throw new \RuntimeException('Unexpected media id.'),
            });
        $this->mediaService = $mediaService;

        $documentGenerator = $this->createMock(DocumentGenerator::class);
        $documentGenerator->expects($this->never())->method('readDocument');
        $this->documentGenerator = $documentGenerator;

        $attachments = $this->createBuilder()->buildAttachments($context, $mailTemplate, $extension, [], null);

        static::assertSame(
            [
                [
                    'id' => $documentId . ':pdf',
                    'content' => 'pdf content',
                    'fileName' => 'invoice.pdf',
                    'mimeType' => 'application/pdf',
                ],
                [
                    'id' => $documentId . ':html',
                    'content' => 'html content',
                    'fileName' => 'invoice.html',
                    'mimeType' => 'text/html',
                ],
            ],
            $attachments
        );
    }

    private function createBuilder(): MailAttachmentsBuilder
    {
        return new MailAttachmentsBuilder(
            $this->mediaService,
            $this->mediaRepository,
            $this->documentGenerator,
            $this->connection,
            $this->documentFileResolver,
        );
    }
}
