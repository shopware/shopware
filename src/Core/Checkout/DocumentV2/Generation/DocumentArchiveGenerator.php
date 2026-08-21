<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

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

    public function archive(DocumentEntity $document, Context $context): ?RenderedDocument
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
            if (!$this->writeDocumentFiles($archive, $document, $context)) {
                return null;
            }

            return new RenderedDocument(
                name: $this->createArchiveName($document),
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
    private function writeDocumentFiles(\ZipArchive $archive, DocumentEntity $document, Context $context): bool
    {
        try {
            $hasFiles = false;

            $mediaIds = [];
            $entryNames = [];
            foreach ($document->getDocumentFiles() ?? [] as $documentFile) {
                $media = $documentFile->getMedia();
                $entryName = $this->createEntryName($documentFile, $media, $document->getId());
                $normalizedEntryName = strtolower($entryName);
                if (isset($entryNames[$normalizedEntryName])) {
                    continue;
                }

                $content = $this->loadMediaContent($media, $context);

                if (!$archive->addFromString($entryName, $content)) {
                    throw DocumentV2Exception::documentArchiveFailed();
                }

                $mediaIds[$media->getId()] = true;
                $entryNames[$normalizedEntryName] = true;
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

                $entryName = $this->createLegacyEntryName($media, $fileExtension, $document->getId());
                $normalizedEntryName = strtolower($entryName);
                if (isset($entryNames[$normalizedEntryName])) {
                    continue;
                }

                $content = $this->loadMediaContent($media, $context);

                if (!$archive->addFromString($entryName, $content)) {
                    throw DocumentV2Exception::documentArchiveFailed();
                }

                $mediaIds[$media->getId()] = true;
                $entryNames[$normalizedEntryName] = true;
                $hasFiles = true;
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

    private function createEntryName(DocumentFileEntity $documentFile, MediaEntity $media, string $documentId): string
    {
        $fileExtension = $media->getFileExtension() ?? $this->documentRendererRegistry->getFileExtension($documentFile->getDocumentFormat());

        if ($fileExtension === null) {
            throw DocumentV2Exception::documentFileExtensionUnavailable($documentId, $documentFile->getDocumentFormat());
        }

        $fileName = $media->getFileName() ?? $documentId;

        return \sprintf('%s.%s', $fileName, $fileExtension);
    }

    private function createLegacyEntryName(MediaEntity $media, string $fileExtension, string $documentId): string
    {
        $fileName = $media->getFileName() ?: $documentId;

        return \sprintf('%s.%s', $fileName, $fileExtension);
    }

    private function createArchiveName(DocumentEntity $document): string
    {
        $documentNumber = $document->getConfig()['documentNumber'] ?? null;
        $fileName = \is_string($documentNumber) && $documentNumber !== '' ? $documentNumber : $document->getId();

        return $fileName . '.zip';
    }
}
