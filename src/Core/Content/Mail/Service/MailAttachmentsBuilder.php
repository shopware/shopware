<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-type MailAttachments array<int, array{id?: string, documentId?: string, content: string, fileName: string, mimeType: string|null}>
 */
#[Package('after-sales')]
class MailAttachmentsBuilder
{
    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param EntityRepository<DocumentCollection> $documentRepository
     */
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly EntityRepository $mediaRepository,
        private readonly DocumentGenerator $documentGenerator,
        private readonly Connection $connection,
        private readonly EntityRepository $documentRepository,
        private readonly LoggerInterface $logger
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

        // Branches on the config shape rather than the feature flag, so already-saved sequences keep
        // working the way they were configured even if the flag is toggled afterwards.
        if (isset($eventConfig['documentTypeIds']) && \is_array($eventConfig['documentTypeIds']) && $eventConfig['documentTypeIds'] !== [] && $orderId) {
            $attachments = $this->mapLegacyAttachments(
                $eventConfig['documentTypeIds'],
                $extensions,
                $attachments,
                $context,
                $orderId
            );
        } else {
            $attachments = $this->mapDocumentAttachments(
                $eventConfig,
                $extensions,
                $attachments,
                $context,
                $orderId
            );
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

    public function getLatestDocumentIdByTechnicalName(string $orderId, string $documentTypeTechnicalName, Context $context): ?string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderId', $orderId))
            ->addFilter(new EqualsFilter('documentType.technicalName', $documentTypeTechnicalName))
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setLimit(1);
        $criteria->setTitle('send-mail::latest-document-by-type');

        return $this->documentRepository->searchIds($criteria, $context)->firstId();
    }

    /**
     * Resolves the latest document per requested type via the legacy document (v1) media file and
     * attaches it as-is - each legacy document only ever has a single generated file.
     *
     * @param array<string> $documentTypeIds
     * @param MailAttachments $attachments
     *
     * @return MailAttachments
     */
    private function mapLegacyAttachments(
        array $documentTypeIds,
        MailSendSubscriberConfig $extensions,
        array $attachments,
        Context $context,
        string $orderId
    ): array {
        $latestDocumentIds = $this->getLatestDocumentsOfTypes($orderId, $documentTypeIds);

        $documentIds = array_unique(array_merge($extensions->getDocumentIds(), $latestDocumentIds));

        if ($documentIds === []) {
            return $attachments;
        }

        $extensions->setDocumentIds($documentIds);

        foreach ($documentIds as $documentId) {
            $attachments = array_merge($attachments, $this->buildLegacyAttachment($documentId, $context));
        }

        return $attachments;
    }

    /**
     * @return MailAttachments
     */
    private function buildLegacyAttachment(string $documentId, Context $context): array
    {
        $document = $this->documentGenerator->readDocument($documentId, $context, fileType: null);

        if ($document === null) {
            return [];
        }

        return [[
            'id' => $documentId,
            'documentId' => $documentId,
            'content' => $document->getContent(),
            'fileName' => $document->getName(),
            'mimeType' => $document->getContentType(),
        ]];
    }

    /**
     * Resolves the latest V2 generated document for the requested type
     * and attaches the requested formats (or all formats if none are specified)
     *
     * @param array<string, mixed> $eventConfig
     * @param MailAttachments $attachments
     *
     * @return MailAttachments
     */
    private function mapDocumentAttachments(
        array $eventConfig,
        MailSendSubscriberConfig $extensions,
        array $attachments,
        Context $context,
        ?string $orderId
    ): array {
        $documentIds = $extensions->getDocumentIds();

        // Document IDs the caller already resolved always attach every generated format
        $requestedFormatsByDocumentId = array_fill_keys($documentIds, null);

        if ($orderId && ($eventConfig['documentType'] ?? '') !== '') {
            $resolvedDocumentId = $this->getLatestDocumentIdByTechnicalName($orderId, $eventConfig['documentType'], $context);

            if ($resolvedDocumentId !== null && !\in_array($resolvedDocumentId, $documentIds, true)) {
                $fileFormats = $eventConfig['fileFormats'] ?? [];
                $requestedFormatsByDocumentId[$resolvedDocumentId] = \is_array($fileFormats) && $fileFormats !== [] ? $fileFormats : null;
                $documentIds[] = $resolvedDocumentId;
            }
        }

        if ($requestedFormatsByDocumentId !== []) {
            $attachments = array_merge($attachments, $this->mapDocumentFilesByFormats($requestedFormatsByDocumentId, $context));
        }

        if ($documentIds !== []) {
            $extensions->setDocumentIds($documentIds);
        }

        return $attachments;
    }

    /**
     * @param array<string, array<string>|null> $requestedFormatsByDocumentId keyed by document id, null attaches every format the document was generated in
     *
     * @return MailAttachments
     */
    private function mapDocumentFilesByFormats(array $requestedFormatsByDocumentId, Context $context): array
    {
        $criteria = (new Criteria(array_keys($requestedFormatsByDocumentId)))->addAssociation('documentFiles.media');
        $criteria->setTitle('send-mail::load-document-files');

        $documents = $this->documentRepository->search($criteria, $context)->getEntities();

        $attachments = [];

        foreach ($requestedFormatsByDocumentId as $documentId => $requestedFormats) {
            // html is surfaced as an a11y link in the mail body instead of being attached
            $attachableFormats = $requestedFormats === null
                ? null
                : array_values(array_diff($requestedFormats, [DocumentFormat::HTML->value]));

            if ($attachableFormats === []) {
                continue;
            }

            $document = $documents->get($documentId);

            if (!$document instanceof DocumentEntity) {
                continue;
            }

            $documentFiles = $document->getDocumentFiles();

            if ($documentFiles === null || $documentFiles->count() === 0) {
                // Document generated before document_v2 - it only has the legacy media file, not document_files.
                $attachments = array_merge($attachments, $this->buildLegacyAttachment($documentId, $context));

                continue;
            }

            $matchedFormat = false;
            $availableFormats = [];

            foreach ($documentFiles as $documentFile) {
                $availableFormats[] = $documentFile->getDocumentFormat();

                if ($documentFile->getDocumentFormat() === DocumentFormat::HTML->value) {
                    continue;
                }

                if ($attachableFormats !== null && !\in_array($documentFile->getDocumentFormat(), $attachableFormats, true)) {
                    continue;
                }

                $matchedFormat = true;

                $media = $documentFile->getMedia();

                $content = $context->scope(
                    Context::SYSTEM_SCOPE,
                    fn (Context $scopedContext): string => $this->mediaService->loadFile($media->getId(), $scopedContext)
                );

                $fileExtension = $media->getFileExtension() ?? $documentFile->getDocumentFormat();

                $attachments[] = [
                    'id' => $documentFile->getId(),
                    'documentId' => $documentId,
                    'content' => $content,
                    'fileName' => ($media->getFileName() ?? $documentId) . '.' . $fileExtension,
                    'mimeType' => $media->getMimeType(),
                ];
            }

            if ($requestedFormats !== null && !$matchedFormat) {
                $this->logMissingDocumentFormat($documentId, $requestedFormats, array_values(array_unique($availableFormats)));
            }
        }

        return $attachments;
    }

    /**
     * @param array<string> $requestedFormats
     * @param array<string> $availableFormats
     */
    private function logMissingDocumentFormat(string $documentId, array $requestedFormats, array $availableFormats): void
    {
        // Logged on the business_events channel, which is persisted to log_entry
        $this->logger->warning(
            'None of the requested document formats were generated for this document, so no attachment was added for it.',
            [
                'documentId' => $documentId,
                'requestedFormats' => $requestedFormats,
                'availableFormats' => $availableFormats,
            ]
        );
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
