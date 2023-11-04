<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly EntityRepository $mediaRepository,
        private readonly DocumentGenerator $documentGenerator,
        private readonly Connection $connection
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

        if (!empty($documentIds)) {
            $extensions->setDocumentIds($documentIds);
            $attachments = $this->mappingAttachments($documentIds, $attachments, $context);
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
                'documentTypeIds' => ArrayParameterType::STRING,
            ]
        );

        return array_column(FetchModeHelper::groupUnique($documents), 'doc_id');
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
