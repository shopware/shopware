<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentFileResolver;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-type MailAttachments array<int, array{id?: string, content: string, fileName: string, mimeType: string|null}>
 */
#[Package('after-sales')]
class MailAttachmentsBuilder
{
    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly EntityRepository $mediaRepository,
        private readonly DocumentGenerator $documentGenerator,
        private readonly Connection $connection,
        private readonly DocumentFileResolver $documentFileResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $eventConfig
     *
     * @return MailAttachments
     */
    public function buildAttachments(
        Context $context,
        MailTemplateEntity $mailTemplate,
        MailSendSubscriberConfig $extensions,
        array $eventConfig,
        ?string $orderId
    ): array {
        $attachments = [];

        foreach ($mailTemplate->getMedia() ?? [] as $mailTemplateMedia) {
            if ($mailTemplateMedia->getMedia() === null || $mailTemplateMedia->getLanguageId() !== $context->getLanguageId()) {
                continue;
            }

            $attachments[] = $this->mediaService->getAttachment(
                $mailTemplateMedia->getMedia(),
                $context
            );
        }

        $documentIds = $extensions->getDocumentIds();

        if (!empty($eventConfig['documentTypeIds']) && \is_array($eventConfig['documentTypeIds']) && $orderId) {
            $latestDocuments = $this->getLatestDocumentsOfTypes($orderId, $eventConfig['documentTypeIds']);

            $documentIds = array_unique(array_merge($documentIds, $latestDocuments));
        }

        if ($documentIds !== []) {
            $extensions->setDocumentIds($documentIds);
            $attachments = $this->mappingAttachments($documentIds, $attachments, $context);
        }

        if ($extensions->getMediaIds() === []) {
            return $this->deduplicateAttachments($attachments);
        }

        $criteria = new Criteria($extensions->getMediaIds());
        $criteria->setTitle('send-mail::load-media');

        $entities = $this->mediaRepository->search($criteria, $context)->getEntities();
        foreach ($entities as $media) {
            $attachments[] = $this->mediaService->getAttachment($media, $context);
        }

        return $this->deduplicateAttachments($attachments);
    }

    /**
     * @param array<string> $documentTypeIds
     *
     * @return array<string>
     */
    public function getLatestDocumentsOfTypes(string $orderId, array $documentTypeIds): array
    {
        $documents = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(hex(`document`.`document_type_id`)) as doc_type,
                LOWER(hex(`document`.`id`)) as doc_id
            FROM `document`
            WHERE `document`.`order_id` = :orderId
            AND `document`.`document_type_id` IN (:documentTypeIds)
            ORDER BY `document`.`created_at` ASC',
            [
                'orderId' => Uuid::fromHexToBytes($orderId),
                'documentTypeIds' => Uuid::fromHexToBytesList($documentTypeIds),
            ],
            [
                'documentTypeIds' => ArrayParameterType::BINARY,
            ]
        );

        /** @var array<string, array{doc_type: string, doc_id: string}> $unique */
        $unique = FetchModeHelper::groupUnique($documents);

        return array_column($unique, 'doc_id');
    }

    /**
     * @param array<string> $documentIds
     * @param MailAttachments $attachments
     *
     * @return MailAttachments
     */
    private function mappingAttachments(array $documentIds, array $attachments, Context $context): array
    {
        foreach ($documentIds as $documentId) {
            $attachments = array_merge($attachments, $this->buildDocumentAttachments($documentId, $context));
        }

        return $attachments;
    }

    /**
     * @return MailAttachments
     */
    private function buildDocumentAttachments(string $documentId, Context $context): array
    {
        if (!Feature::isActive('DOCUMENT_GENERATION_REWORK')) {
            return $this->buildLegacyDocumentAttachment($documentId, $context);
        }

        $document = $this->documentFileResolver->loadDocument($documentId, $context);

        if ($document === null) {
            return [];
        }

        $attachments = [];

        // Attach every format the document was generated in
        foreach ($document->getDocumentFiles() ?? [] as $documentFile) {
            $media = $documentFile->getMedia();

            $content = $context->scope(
                Context::SYSTEM_SCOPE,
                fn (Context $scopedContext): string => $this->mediaService->loadFile($media->getId(), $scopedContext),
            );

            $fileExtension = $media->getFileExtension() ?? $documentFile->getDocumentFormat();

            $attachments[] = [
                'id' => $documentId . ':' . $documentFile->getDocumentFormat(),
                'content' => $content,
                'fileName' => ($media->getFileName() ?? $documentId) . '.' . $fileExtension,
                'mimeType' => $media->getMimeType(),
            ];
        }

        return $attachments;
    }

    /**
     * @return MailAttachments
     */
    private function buildLegacyDocumentAttachment(string $documentId, Context $context): array
    {
        $document = $this->documentGenerator->readDocument($documentId, $context, fileType: null);

        if ($document === null) {
            return [];
        }

        return [[
            'id' => $documentId,
            'content' => $document->getContent(),
            'fileName' => $document->getName(),
            'mimeType' => $document->getContentType(),
        ]];
    }

    /**
     * @param MailAttachments $attachments
     *
     * @return MailAttachments
     */
    private function deduplicateAttachments(array $attachments): array
    {
        $seen = [];
        $deduplicated = [];

        foreach ($attachments as $attachment) {
            $key = $attachment['id'] ?? Hasher::hash(
                json_encode([
                    $attachment['fileName'],
                    $attachment['mimeType'] ?? '',
                    Hasher::hash($attachment['content'], 'sha1'),
                ], \JSON_THROW_ON_ERROR),
                'sha1'
            );

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduplicated[] = $attachment;
        }

        return $deduplicated;
    }
}
