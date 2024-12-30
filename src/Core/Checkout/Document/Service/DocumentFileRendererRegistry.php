<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
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
        $content = null;

        foreach ($this->renderers as $renderer) {
            $renderer->templateRenderer($document->getTemplateOptions(), $document->getHtml());
            if ($renderer->getContentType() !== $document->getContentType()) {
                continue;
            }

            $content = $renderer->render($document);
        }

        if (!$content) {
            throw DocumentException::invalidDocumentRendererFileExtension($document->getFileExtension());
        }

        return $content;
    }
}
