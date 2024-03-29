<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
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
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(MailAttachmentsBuilder::class)]
class MailAttachmentsBuilderTest extends TestCase
{
    private MockObject&MediaService $mediaService;

    private MockObject&EntityRepository $mediaRepository;

    private MockObject&DocumentGenerator $documentGenerator;

    private Connection&MockObject $connection;

    private MailAttachmentsBuilder $attachmentsBuilder;

    protected function setUp(): void
    {
        $this->mediaService = $this->createMock(MediaService::class);
        $this->mediaRepository = $this->createMock(EntityRepository::class);
        $this->documentGenerator = $this->createMock(DocumentGenerator::class);
        $this->connection = $this->createMock(Connection::class);

        $this->attachmentsBuilder = new MailAttachmentsBuilder(
            $this->mediaService,
            $this->mediaRepository,
            $this->documentGenerator,
            $this->connection
        );
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

        $this->mediaService
            ->expects(static::exactly(2))
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

        $attachments = $this->attachmentsBuilder->buildAttachments($context, $mailTemplate, $extension, [], Uuid::randomHex());

        static::assertEquals(
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

        $this->connection
            ->expects(static::once())
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

        $document = new RenderedDocument();
        $document->setContent('');
        $this->documentGenerator
            ->expects(static::exactly(4))
            ->method('readDocument')
            ->willReturn($document);

        $criteria = new Criteria($extension->getMediaIds());
        $criteria->setTitle('send-mail::load-media');
        $entities = array_map(static function (string $id): MediaEntity {
            $media = new MediaEntity();
            $media->setId($id);

            return $media;
        }, $extension->getMediaIds());

        $this->mediaRepository
            ->expects(static::once())
            ->method('search')
            ->with($criteria, $context)
            ->willReturn(new EntitySearchResult('media', 2, new MediaCollection($entities), null, $criteria, $context));

        $this->mediaService
            ->expects(static::exactly(2))
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

        $attachments = $this->attachmentsBuilder->buildAttachments($context, $mailTemplate, $extension, $eventConfig, $orderId);

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
}
