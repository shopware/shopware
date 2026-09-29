<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Shared\MailFlow\DocumentResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

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
        private readonly EntityRepository $documentRepository,
        private readonly LoggerInterface $logger,
        private readonly DocumentResolver $documentResolver
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

        $resolvedDocuments = $this->documentResolver->resolve(
            $eventConfig,
            $extensions->getDocumentIds(),
            $orderId,
            $context,
        );

        if ($resolvedDocuments !== []) {
            $extensions->setDocumentIds(array_keys($resolvedDocuments));

            $attachments = array_merge($attachments, $this->mapDocumentFilesByFormats($resolvedDocuments, $context));
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

            // The accessible html is never attached, it is linked in the mail body instead, so a document
            // that holds nothing else contributes no attachment at all. The link replaces the attachment only for
            // mail templates that render the a11yDocuments block, so the log is useful in case the template
            // does not render the block and the user expects an attachment to be present.
            if (!$matchedFormat && ($requestedFormats !== null || $availableFormats !== [])) {
                $this->logMissingDocumentFormat($documentId, $requestedFormats ?? [], array_values(array_unique($availableFormats)));
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
            'No attachable document format was generated for this document, so no attachment was added for it.',
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
