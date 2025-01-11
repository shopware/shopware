<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class DocumentFileRendererRegistry
{
    /**
     * Constructor for DocumentFileRendererRegistry.
     *
     * @internal
     *
     * @param AbstractDocumentTypeRenderer[] $renderers An iterable collection of document type renderers.
     */
    public function __construct(protected iterable $renderers)
    {
    }

    /**
     * Renders a document using the appropriate renderer based on the document's content type.
     *
     * @param RenderedDocument $document The document to be rendered.
     *
     * @throws DocumentException If no renderer matches the document's file extension.
     *
     * @return string The rendered document content.
     */
    public function render(RenderedDocument $document): string
    {
        $renderers = $this->renderers instanceof \Traversable ? iterator_to_array($this->renderers) : $this->renderers;
        $renderer = $renderers[$document->getFileExtension()];

        if ($renderer instanceof AbstractDocumentTypeRenderer) {
            return $renderer->render($document);
        }

        throw DocumentException::invalidDocumentRendererFileExtension($document->getFileExtension());
    }
}
