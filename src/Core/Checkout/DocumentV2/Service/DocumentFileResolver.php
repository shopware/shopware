<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Service;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver\AbstractFileResolver;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver\DocumentV2FileResolver;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver\LegacyDocumentFileResolver;
use Shopware\Core\Checkout\DocumentV2\Struct\ResolvedDocumentFile;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final class DocumentFileResolver
{
    public function __construct(
        private readonly LegacyDocumentFileResolver $legacyDocumentFileResolver,
        private readonly DocumentV2FileResolver $documentV2FileResolver,
    ) {
    }

    public function resolve(
        DocumentEntity $document,
        string $format,
        string $preferredSource = ResolvedDocumentFile::SOURCE_V2,
    ): ?ResolvedDocumentFile {
        $format = $format === 'xml' ? DocumentFormat::ZUGFERD_XML->value : $format;

        foreach ($this->getOrderedResolvers($preferredSource) as $resolver) {
            $resolvedFile = $resolver->resolve($document, $format);
            if ($resolvedFile !== null) {
                return $resolvedFile;
            }
        }

        return null;
    }

    /**
     * @return list<AbstractFileResolver>
     */
    private function getOrderedResolvers(string $preferredSource): array
    {
        return $preferredSource === ResolvedDocumentFile::SOURCE_LEGACY
            ? [$this->legacyDocumentFileResolver, $this->documentV2FileResolver]
            : [$this->documentV2FileResolver, $this->legacyDocumentFileResolver];
    }
}
