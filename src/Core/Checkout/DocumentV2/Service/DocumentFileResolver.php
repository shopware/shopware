<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Service;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Struct\ResolvedDocumentFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed.
 */
#[Package('after-sales')]
final class DocumentFileResolver
{
    public function resolve(
        DocumentEntity $document,
        string $format,
        string $preferredSource = ResolvedDocumentFile::SOURCE_V2,
    ): ?ResolvedDocumentFile {
        $format = $format === 'xml' ? DocumentFormat::ZUGFERD_XML->value : $format;

        if ($preferredSource === ResolvedDocumentFile::SOURCE_LEGACY) {
            return $this->resolveLegacyFile($document, $format) ?? $this->resolveV2File($document, $format);
        }

        return $this->resolveV2File($document, $format) ?? $this->resolveLegacyFile($document, $format);
    }

    private function resolveV2File(DocumentEntity $document, string $format): ?ResolvedDocumentFile
    {
        foreach ($document->getDocumentFiles() ?? [] as $documentFile) {
            if ($documentFile->getDocumentFormat() !== $format) {
                continue;
            }

            return $this->createResolvedFile(
                $document,
                $documentFile->getMedia(),
                $format,
                ResolvedDocumentFile::SOURCE_V2,
            );
        }

        return null;
    }

    private function resolveLegacyFile(DocumentEntity $document, string $format): ?ResolvedDocumentFile
    {
        if ($format === DocumentFormat::ZUGFERD_EMBEDDED_PDF->value && !$this->isLegacyEmbeddedDocument($document)) {
            return null;
        }

        $fileExtension = DocumentFormat::tryFrom($format)?->fileExtension() ?? $format;
        if ($fileExtension === '') {
            return null;
        }

        foreach ([$document->getDocumentMediaFile(), $document->getDocumentA11yMediaFile()] as $media) {
            if ($media?->getFileExtension() === null || strcasecmp($media->getFileExtension(), $fileExtension) !== 0) {
                continue;
            }

            return $this->createResolvedFile(
                $document,
                $media,
                $format,
                ResolvedDocumentFile::SOURCE_LEGACY,
            );
        }

        return null;
    }

    private function createResolvedFile(
        DocumentEntity $document,
        MediaEntity $media,
        string $format,
        string $source,
    ): ResolvedDocumentFile {
        $documentNumber = $document->getConfig()['documentNumber'] ?? null;
        $fileName = $media->getFileName();
        if ($fileName === null || $fileName === '') {
            $fileName = \is_string($documentNumber) && $documentNumber !== '' ? $documentNumber : $document->getId();
        }

        return new ResolvedDocumentFile(
            media: $media,
            format: $format,
            fileExtension: $media->getFileExtension() ?? DocumentFormat::tryFrom($format)?->fileExtension() ?? '',
            mimeType: $media->getMimeType() ?? DocumentFormat::tryFrom($format)?->mimeType() ?? 'application/octet-stream',
            fileName: $fileName,
            source: $source,
        );
    }

    private function isLegacyEmbeddedDocument(DocumentEntity $document): bool
    {
        return str_contains(strtolower($document->getDocumentType()?->getTechnicalName() ?? ''), 'embedded');
    }
}
