<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Service;

use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
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
        private DocumentFileResolver $documentFileResolver,
    ) {
    }

    public function read(
        string $documentId,
        Context $context,
        string $deepLinkCode = '',
        ?string $format = null
    ): RenderedDocument {
        $criteria = (new Criteria([$documentId]))
            ->addAssociations([
                'documentFiles.media',
                'documentMediaFile',
                'documentA11yMediaFile',
                'documentType',
            ]);

        if ($deepLinkCode !== '') {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $deepLinkCode));
        }

        $document = $this->documentRepository->search($criteria, $context)->getEntities()->first();
        if (!$document instanceof DocumentEntity) {
            throw DocumentV2Exception::documentNotFound($documentId);
        }

        $resolvedFormat = $format ?? $this->resolveDefaultFormat($document);
        if ($resolvedFormat === null) {
            throw DocumentV2Exception::documentFormatUnavailable($documentId, $format ?? 'default');
        }

        $resolvedFile = $this->documentFileResolver->resolve($document, $resolvedFormat);
        if ($resolvedFile === null) {
            throw DocumentV2Exception::documentFormatUnavailable($documentId, $format ?? 'default');
        }

        $fileExtension = $resolvedFile->fileExtension;
        if ($fileExtension === '') {
            $fileExtension = $this->documentRendererRegistry->getFileExtension($resolvedFile->format);
            if ($fileExtension === null) {
                throw DocumentV2Exception::documentFileExtensionUnavailable($documentId, $resolvedFile->format);
            }
        }

        $content = $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $scoped): string => $this->mediaService->loadFile($resolvedFile->media->getId(), $scoped),
        );

        $renderedDocument = new RenderedDocument(
            name: $resolvedFile->fileName . '.' . $fileExtension,
            fileExtension: $fileExtension,
            contentType: $resolvedFile->mimeType,
        );

        $renderedDocument->setContent($content);

        return $renderedDocument;
    }

    /**
     * Without an explicit format the first stored file wins, falling back to the media written by
     * document generation v1.
     */
    private function resolveDefaultFormat(DocumentEntity $document): ?string
    {
        $documentFiles = $document->getDocumentFiles();
        if ($documentFiles !== null && $documentFiles->count() > 0) {
            return $documentFiles->first()?->getDocumentFormat();
        }

        $legacyExtension = $document->getDocumentMediaFile()?->getFileExtension();
        if ($legacyExtension !== null && $legacyExtension !== '') {
            return $legacyExtension;
        }

        return $document->getDocumentA11yMediaFile() !== null ? 'html' : null;
    }
}
