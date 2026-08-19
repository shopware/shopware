<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
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
        private DocumentRendererRegistry $documentRendererRegistry,
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

        $documentFile = $this->findDocumentFileByFormat($document, $format);
        $media = $documentFile?->getMedia();
        if ($documentFile === null || $media === null) {
            throw DocumentV2Exception::documentFormatUnavailable($documentId, $format ?? 'default');
        }

        $resolvedFormat = $documentFile->getDocumentFormat();

        $fileExtension = $media->getFileExtension() ?? $this->documentRendererRegistry->getFileExtension($resolvedFormat);
        if ($fileExtension === null) {
            throw DocumentV2Exception::documentFileExtensionUnavailable($documentId, $resolvedFormat);
        }

        $content = $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $scoped): string => $this->mediaService->loadFile($media->getId(), $scoped),
        );

        $renderedDocument = new RenderedDocument(
            name: ($media->getFileName() ?? $documentId) . '.' . $fileExtension,
            fileExtension: $fileExtension,
            contentType: $media->getMimeType() ?? 'application/octet-stream',
        );

        $renderedDocument->setContent($content);

        return $renderedDocument;
    }

    private function findDocumentFileByFormat(DocumentEntity $document, ?string $format): ?DocumentFileEntity
    {
        $documentFiles = $document->getDocumentFiles();
        if ($documentFiles === null || $documentFiles->count() === 0) {
            return null;
        }

        if ($format === null) {
            return $documentFiles->first();
        }

        foreach ($documentFiles as $documentFile) {
            if ($documentFile->getDocumentFormat() === $format) {
                return $documentFile;
            }
        }

        return null;
    }
}
