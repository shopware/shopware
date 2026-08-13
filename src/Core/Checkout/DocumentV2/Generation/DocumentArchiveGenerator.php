<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
final class DocumentArchiveGenerator
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly Filesystem $filesystem,
        private readonly DocumentRendererRegistry $documentRendererRegistry,
    ) {
    }

    public function archive(DocumentCollection $documents, Context $context): ?RenderedDocument
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'document-v2-');

        if ($tempFile === false) {
            throw DocumentV2Exception::documentArchiveFailed();
        }

        $archive = new \ZipArchive();

        if ($archive->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->filesystem->remove($tempFile);

            throw DocumentV2Exception::documentArchiveFailed();
        }

        try {
            if (!$this->writeDocumentFiles($archive, $documents, $context)) {
                return null;
            }

            return new RenderedDocument(
                name: $this->createArchiveName($documents),
                fileExtension: 'zip',
                contentType: 'application/zip',
                content: $this->filesystem->readFile($tempFile),
            );
        } finally {
            $this->filesystem->remove($tempFile);
        }
    }

    /**
     * @return bool whether any files were written to the archive
     */
    private function writeDocumentFiles(\ZipArchive $archive, DocumentCollection $documents, Context $context): bool
    {
        try {
            $hasFiles = false;

            foreach ($documents as $document) {
                foreach ($document->getDocumentFiles() ?? [] as $documentFile) {
                    $media = $documentFile->getMedia();
                    $entryName = $this->createEntryName($document, $documentFile, $media);
                    $content = $this->loadMediaContent($media, $context);

                    if (!$archive->addFromString($entryName, $content)) {
                        throw DocumentV2Exception::documentArchiveFailed();
                    }

                    $hasFiles = true;
                }
            }

            return $hasFiles;
        } finally {
            $archive->close();
        }
    }

    private function loadMediaContent(MediaEntity $media, Context $context): string
    {
        return $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $scopedContext): string => $this->mediaService->loadFile($media->getId(), $scopedContext),
        );
    }

    /**
     * Prefixes every file with the order number, since the file name itself is already unique
     */
    private function createEntryName(DocumentEntity $document, DocumentFileEntity $documentFile, MediaEntity $media): string
    {
        $fileExtension = $media->getFileExtension() ?? $this->documentRendererRegistry->getFileExtension($documentFile->getDocumentFormat());

        if ($fileExtension === null) {
            throw DocumentV2Exception::documentFileExtensionUnavailable($document->getId(), $documentFile->getDocumentFormat());
        }

        $orderNumber = $document->getOrder()?->getOrderNumber() ?? $document->getOrderId();
        $fileName = $media->getFileName() ?? $document->getId();

        return \sprintf('%s_%s.%s', $orderNumber, $fileName, $fileExtension);
    }

    private function createArchiveName(DocumentCollection $documents): string
    {
        if ($documents->count() === 1) {
            $document = $documents->first();
            \assert($document !== null);

            $documentNumber = $document->getConfig()['documentNumber'] ?? null;
            $fileName = \is_string($documentNumber) && $documentNumber !== '' ? $documentNumber : $document->getId();

            return $fileName . '.zip';
        }

        return 'documents.zip';
    }
}
