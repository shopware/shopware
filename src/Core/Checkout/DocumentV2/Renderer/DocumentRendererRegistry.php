<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentRendererRegistry
{
    /**
     * @param iterable<AbstractDocumentRenderer> $documentRenderers
     */
    public function __construct(
        private iterable $documentRenderers,
    ) {
    }

    /**
     * Returns the renderer used for one exact format and document type combination.
     *
     * @throws DocumentV2Exception
     */
    public function getRenderer(string $format, string $documentType): AbstractDocumentRenderer
    {
        $renderers = $this->mapRenderersByFormat($documentType);

        if (!isset($renderers[$format])) {
            throw DocumentV2Exception::rendererNotFound($format, $documentType);
        }

        return $renderers[$format];
    }

    /**
     * Builds a format => renderer map for all renderers that support the given document type.
     *
     * @return array<string, AbstractDocumentRenderer>
     */
    public function mapRenderersByFormat(string $documentType): array
    {
        $renderers = [];

        foreach ($this->documentRenderers as $renderer) {
            if (!$renderer->supports($documentType)) {
                continue;
            }

            $format = $renderer->getFormat();

            if (isset($renderers[$format])) {
                throw DocumentV2Exception::duplicateRenderer($format, $documentType);
            }

            $renderers[$format] = $renderer;
        }

        return $renderers;
    }
}
