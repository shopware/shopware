<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Extension\HtmlRendererExtension;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('after-sales')]
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
        private readonly string $rootDir,
        private readonly ExtensionDispatcher $extensions,
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

    private function getContent(RenderedDocument $document): string
    {
        if (!$document->getOrder() || !$document->getContext()) {
            throw DocumentException::documentGenerationException('No options provided for rendering the document.');
        }

        $config = DocumentConfigurationFactory::mergeConfiguration(
            new DocumentConfiguration(),
            $document->getConfig(),
        );

        $config->merge([
            'fileType' => self::FILE_EXTENSION,
            'itemsPerPage' => 1000,
        ]);

        $language = $document->getOrder()->getLanguage();

        return $this->documentTemplateRenderer->render(
            $document->getTemplate(),
            [
                'order' => $document->getOrder(),
                'config' => $config,
                'rootDir' => $this->rootDir,
                'context' => $document->getContext(),
            ],
            $document->getContext(),
            $document->getOrder()->getSalesChannelId(),
            $document->getOrder()->getLanguageId(),
            $language?->getLocale()?->getCode(),
        );
    }

    private function _render(RenderedDocument $document): string
    {
        $fileTypes = $document->getConfig()['fileTypes'] ?? [self::FILE_EXTENSION];
        if (!\in_array(self::FILE_EXTENSION, $fileTypes, true)) {
            return '';
        }

        $content = $this->getContent($document);

        $document->setContentType(self::FILE_CONTENT_TYPE);
        $document->setFileExtension(self::FILE_EXTENSION);
        $document->setContent($content);

        return $content;
    }
}
