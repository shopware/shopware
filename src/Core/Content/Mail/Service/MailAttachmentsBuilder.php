<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-type MailAttachments array<int, array{id?: string, content: string, fileName: string, mimeType: string|null}>
 */
#[Package('system-settings')]
class MailAttachmentsBuilder
{
    private MediaService $mediaService;

    private EntityRepositoryInterface $mediaRepository;

    private EntityRepositoryInterface $documentRepository;

    private DocumentGenerator $documentGenerator;

    private DocumentService $documentService;

    private Connection $connection;

    public function __construct(
        MediaService $mediaService,
        EntityRepositoryInterface $mediaRepository,
        EntityRepositoryInterface $documentRepository,
        DocumentGenerator $documentGenerator,
        DocumentService $documentService,
        Connection $connection
    ) {
        $this->mediaService = $mediaService;
        $this->mediaRepository = $mediaRepository;
        $this->documentRepository = $documentRepository;
        $this->documentGenerator = $documentGenerator;
        $this->documentService = $documentService;
        $this->connection = $connection;
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

        if (!empty($documentIds)) {
            $extensions->setDocumentIds($documentIds);
            if (Feature::isActive('v6.5.0.0')) {
                $attachments = $this->mappingAttachments($documentIds, $attachments, $context);
            } else {
                $attachments = $this->buildOrderAttachments($documentIds, $attachments, $context);
            }
        }

        if (empty($extensions->getMediaIds())) {
            return $attachments;
        }

        $criteria = new Criteria($extensions->getMediaIds());
        $criteria->setTitle('send-mail::load-media');

        /** @var MediaCollection<MediaEntity> $entities */
        $entities = $this->mediaRepository->search($criteria, $context);

        foreach ($entities as $media) {
            $attachments[] = $this->mediaService->getAttachment($media, $context);
        }

        return $attachments;
    }

    /**
     * @param array<string> $documentIds
     * @param MailAttachments $attachments
     *
     * @return MailAttachments
     */
    private function buildOrderAttachments(array $documentIds, array $attachments, Context $context): array
    {
        $criteria = new Criteria($documentIds);
        $criteria->setTitle('send-mail::load-attachments');
        $criteria->addAssociation('documentMediaFile');
        $criteria->addAssociation('documentType');

        /** @var DocumentCollection $documents */
        $documents = $this->documentRepository->search($criteria, $context)->getEntities();

        return $this->mappingAttachmentsInfo($documents, $attachments, $context);
    }

    /**
     * @param array<string> $documentTypeIds
     *
     * @return array<string>
     */
    private function getLatestDocumentsOfTypes(string $orderId, array $documentTypeIds): array
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
                'documentTypeIds' => Connection::PARAM_STR_ARRAY,
            ]
        );

        return array_column(FetchModeHelper::groupUnique($documents), 'doc_id');
    }

    /**
     * @param MailAttachments $attachments
     *
     * @return MailAttachments
     */
    private function mappingAttachmentsInfo(DocumentCollection $documents, array $attachments, Context $context): array
    {
        foreach ($documents as $document) {
            $documentId = $document->getId();
            $document = $this->documentService->getDocument($document, $context);

            $attachments[] = [
                'id' => $documentId,
                'content' => $document->getFileBlob(),
                'fileName' => $document->getFilename(),
                'mimeType' => $document->getContentType(),
            ];
        }

        return $attachments;
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
            $document = $this->documentGenerator->readDocument($documentId, $context);

            if ($document === null) {
                continue;
            }

            $attachments[] = [
                'id' => $documentId,
                'content' => $document->getContent(),
                'fileName' => $document->getName(),
                'mimeType' => $document->getContentType(),
            ];
        }

        return $attachments;
    }
}
