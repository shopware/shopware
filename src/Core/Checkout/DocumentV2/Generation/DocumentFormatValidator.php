<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentFormatValidator
{
    public function __construct(
        private DocumentRendererRegistry $documentRendererRegistry,
    ) {
    }

    /**
     * @param list<string> $formats
     */
    public function validate(string $documentType, array $formats): void
    {
        $supportedFormats = array_keys($this->documentRendererRegistry->mapRenderersByFormat($documentType));

        foreach ($formats as $format) {
            if (!\in_array($format, $supportedFormats, true)) {
                throw DocumentV2Exception::unsupportedDocumentFormat($format, $documentType);
            }
        }
    }
}
