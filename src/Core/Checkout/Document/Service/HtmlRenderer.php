<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\Extension\HtmlRendererExtension;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('checkout')]
class HtmlRenderer extends AbstractDocumentTypeRenderer
{
    public const FILE_EXTENSION = 'html';

    public const FILE_CONTENT_TYPE = 'text/html';

    /**
     * Constructor for HtmlRenderer.
     *
     * @internal
     *
     * @param DocumentTemplateRenderer $documentTemplateRenderer The renderer for document templates.
     */
    public function __construct(
        private readonly DocumentTemplateRenderer $documentTemplateRenderer,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getContentType(): string
    {
        return self::FILE_CONTENT_TYPE;
    }

    public function render(RenderedDocument $document): string
    {
        return $this->extensions->publish(
            name: HtmlRendererExtension::NAME,
            extension: new HtmlRendererExtension($document),
            function: $this->_render(...)
        );
    }

    public function getDecorated(): AbstractDocumentTypeRenderer
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * Gets the content for the document based on the provided options.
     *
     * @param array<mixed> $options The options for rendering the document.
     *
     * @return string The content of the document.
     */
    private function getContent(array $options): string
    {
        if (empty($options)) {
            return '';
        }

        // override the config to set the correct file type
        foreach ($options as $option) {
            if (isset($option['config']) && $option['config'] instanceof DocumentConfiguration) {
                $option['config']->merge([
                    'fileType' => self::FILE_EXTENSION, // we need to add the config to adjust the CSS from twig file
                    'itemsPerPage' => 1000, // we need to render the whole document at once
                ]);

                break;
            }
        }

        return $this->documentTemplateRenderer->render(
            ...$options,
        );
    }

    private function _render(RenderedDocument $document): string
    {
        $content = $this->getContent($document->getTemplateOptions());

        $document->setContentType(self::FILE_CONTENT_TYPE);
        $document->setFileExtension(self::FILE_EXTENSION);
        $document->setContent($content);

        return $content;
    }
}
