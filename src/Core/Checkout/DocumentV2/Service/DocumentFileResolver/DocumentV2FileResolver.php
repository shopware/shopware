<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\Struct\ResolvedDocumentFile;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentV2FileResolver extends AbstractFileResolver
{
    public function resolve(DocumentEntity $document, string $format): ?ResolvedDocumentFile
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
}
