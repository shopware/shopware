<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentReader
{
    /**
     * @param EntityRepository<DocumentCollection> $documentRepository
     */
    public function __construct(
        private EntityRepository $documentRepository,
        private MediaService $mediaService,
    ) {
    }

    public function read(
        string $documentId,
        Context $context,
        string $deepLinkCode = '',
        ?string $format = null
    ): RenderedDocument {
        $criteria = (new Criteria([$documentId]))
            ->addAssociation('documentFiles.media');

        if ($deepLinkCode !== '') {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $deepLinkCode));
        }

        $document = $this->documentRepository->search($criteria, $context)->getEntities()->first();
        if (!$document instanceof DocumentEntity) {
            throw DocumentV2Exception::documentNotFound($documentId);
        }

        $media = $this->findMediaByFormat($document, $format);
        if ($media === null) {
            throw DocumentV2Exception::documentFormatUnavailable($documentId, $format ?? 'default');
        }

        $content = $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $scoped): string => $this->mediaService->loadFile($media->getId(), $scoped),
        );

        $renderedDocument = new RenderedDocument(
            name: $media->getFileName() . '.' . $media->getFileExtension(),
            fileExtension: $media->getFileExtension() ?? (string) $format,
            contentType: $media->getMimeType(),
        );

        $renderedDocument->setContent($content);

        return $renderedDocument;
    }

    private function findMediaByFormat(DocumentEntity $document, ?string $format): ?MediaEntity
    {
        $documentFiles = $document->getDocumentFiles();
        if ($documentFiles === null || $documentFiles->count() === 0) {
            return null;
        }

        if ($format === null) {
            return $documentFiles->first()?->getMedia();
        }

        foreach ($documentFiles as $documentFile) {
            if ($documentFile->getDocumentFormat() === $format) {
                return $documentFile->getMedia();
            }
        }

        return null;
    }
}
