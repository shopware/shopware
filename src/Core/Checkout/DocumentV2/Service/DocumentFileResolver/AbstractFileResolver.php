<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Struct\ResolvedDocumentFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
abstract class AbstractFileResolver
{
    abstract public function resolve(DocumentEntity $document, string $format): ?ResolvedDocumentFile;

    protected function createResolvedFile(
        DocumentEntity $document,
        MediaEntity $media,
        string $format,
        string $source,
    ): ?ResolvedDocumentFile {
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
}
