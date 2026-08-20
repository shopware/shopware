<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Struct\ResolvedDocumentFile;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class LegacyDocumentFileResolver extends AbstractFileResolver
{
    public function resolve(DocumentEntity $document, string $format): ?ResolvedDocumentFile
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

    private function isLegacyEmbeddedDocument(DocumentEntity $document): bool
    {
        return str_contains(strtolower($document->getDocumentType()?->getTechnicalName() ?? ''), 'embedded');
    }
}
