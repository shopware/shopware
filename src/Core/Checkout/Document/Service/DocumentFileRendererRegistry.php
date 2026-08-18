<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.9.0 - Will be removed.
 */
#[Package('after-sales')]
class DocumentFileRendererRegistry
{
    /**
     * @internal
     *
     * @param AbstractDocumentTypeRenderer[] $renderers
     */
    public function __construct(protected iterable $renderers)
    {
    }

    public function render(RenderedDocument $document): string
    {
        Feature::triggerDeprecationOrThrow('v6.9.0.0', 'DocumentFileRendererRegistry is deprecated and will be removed with document generation v1.');

        $renderers = $this->renderers instanceof \Traversable ? iterator_to_array($this->renderers) : $this->renderers;
        $renderer = $renderers[$document->getFileExtension()] ?? null;

        if ($renderer instanceof AbstractDocumentTypeRenderer) {
            return $renderer->render($document);
        }

        throw DocumentException::unsupportedDocumentFileExtension($document->getFileExtension());
    }
}
