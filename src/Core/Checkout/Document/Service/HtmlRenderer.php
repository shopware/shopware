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
     * @internal
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
     * @param array<mixed> $templateOptions
     */
    public function templateRenderer(array $templateOptions, string $html = ''): void
    {
        $this->htmlRenderer[self::FILE_EXTENSION] = $html;

        if (empty($templateOptions)) {
            return;
        }

        foreach ($templateOptions as $option) {
            if (isset($option['config']) && $option['config'] instanceof DocumentConfiguration) {
                $option['config']->merge([
                    'fileType' => self::FILE_EXTENSION,
                    'itemsPerPage' => 1000,
                ]);

                break;
            }
        }

        $this->htmlRenderer[self::FILE_EXTENSION] = $this->documentTemplateRenderer->render(
            ...$templateOptions,
        );
    }

    private function _render(RenderedDocument $document): string
    {
        $document->setContentType(self::FILE_CONTENT_TYPE);
        $document->setFileExtension(self::FILE_EXTENSION);

        return $this->htmlRenderer[self::FILE_EXTENSION];
    }
}
