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

    public function getRenderer(string $format, string $documentType): AbstractDocumentRenderer
    {
        foreach ($this->documentRenderers as $renderer) {
            if ($renderer->supports($documentType) && $renderer->getFormat() === $format) {
                return $renderer;
            }
        }

        throw DocumentV2Exception::rendererNotFound($format, $documentType);
    }

    /**
     * @return array<string, AbstractDocumentRenderer>
     */
    public function mapRenderersByFormat(string $documentType): array
    {
        $renderers = [];

        foreach ($this->documentRenderers as $renderer) {
            if ($renderer->supports($documentType)) {
                $renderers[$renderer->getFormat()] = $renderer;
            }
        }

        return $renderers;
    }
}
