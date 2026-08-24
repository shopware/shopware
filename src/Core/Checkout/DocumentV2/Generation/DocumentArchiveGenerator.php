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

            // Entry names are unique across the whole archive, so a collision would silently
            // overwrite a previously added file instead of adding a second one.
            $entryNames = [];

            foreach ($documents as $document) {
                $hasFiles = $this->writeDocument($archive, $document, $entryNames, $context) || $hasFiles;
            }

            return $hasFiles;
        } finally {
            $archive->close();
        }
    }

    /**
     * Writes every stored format of a single document, falling back to the documents written by
     * document generation v1, which kept the media on the document itself instead of in document_file.
     *
     * @param array<string, true> $entryNames
     *
     * @return bool whether any files were written for this document
     */
    private function writeDocument(
        \ZipArchive $archive,
        DocumentEntity $document,
        array &$entryNames,
        Context $context,
    ): bool {
        $hasFiles = false;

        // A document can hold the same media both in document_file and in one of the legacy
        // fields, so it must not be added to the archive twice.
        $mediaIds = [];

        foreach ($document->getDocumentFiles() ?? [] as $documentFile) {
            $media = $documentFile->getMedia();
            $entryName = $this->createEntryName($document, $documentFile, $media);

            if (!$this->addFile($archive, $entryName, $media, $entryNames, $context)) {
                continue;
            }

            $mediaIds[$media->getId()] = true;
            $hasFiles = true;
        }

        foreach ([$document->getDocumentMediaFile(), $document->getDocumentA11yMediaFile()] as $media) {
            if ($media === null || isset($mediaIds[$media->getId()])) {
                continue;
            }

            $fileExtension = $media->getFileExtension();
            if ($fileExtension === null || $fileExtension === '') {
                continue;
            }

            $entryName = $this->createLegacyEntryName($document, $media, $fileExtension);

            if (!$this->addFile($archive, $entryName, $media, $entryNames, $context)) {
                continue;
            }

            $mediaIds[$media->getId()] = true;
            $hasFiles = true;
        }

        return $hasFiles;
    }

    /**
     * @param array<string, true> $entryNames
     *
     * @return bool whether the file was added, false if the entry name is already taken
     */
    private function addFile(
        \ZipArchive $archive,
        string $entryName,
        MediaEntity $media,
        array &$entryNames,
        Context $context,
    ): bool {
        $normalizedEntryName = strtolower($entryName);

        if (isset($entryNames[$normalizedEntryName])) {
            return false;
        }

        if (!$archive->addFromString($entryName, $this->loadMediaContent($media, $context))) {
            throw DocumentV2Exception::documentArchiveFailed();
        }

        $entryNames[$normalizedEntryName] = true;

        return true;
    }

    private function loadMediaContent(MediaEntity $media, Context $context): string
    {
        return $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $scopedContext): string => $this->mediaService->loadFile($media->getId(), $scopedContext),
        );
    }

    /**
     * Prefixes every file with the order number, since the same document number can be issued
     * for different orders and the file name itself is only unique within one order.
     */
    private function createEntryName(DocumentEntity $document, DocumentFileEntity $documentFile, MediaEntity $media): string
    {
        $fileExtension = $media->getFileExtension() ?? $this->documentRendererRegistry->getFileExtension($documentFile->getDocumentFormat());

        if ($fileExtension === null) {
            throw DocumentV2Exception::documentFileExtensionUnavailable($document->getId(), $documentFile->getDocumentFormat());
        }

        return $this->createFileName($document, $media, $fileExtension);
    }

    private function createLegacyEntryName(DocumentEntity $document, MediaEntity $media, string $fileExtension): string
    {
        return $this->createFileName($document, $media, $fileExtension);
    }

    private function createFileName(DocumentEntity $document, MediaEntity $media, string $fileExtension): string
    {
        $orderNumber = $document->getOrder()?->getOrderNumber() ?? $document->getOrderId();
        $fileName = $media->getFileName() ?: $document->getId();

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
