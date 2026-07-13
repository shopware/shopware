<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Checkout\DocumentV2\Template\DocumentTemplateRenderer;
use Shopware\Core\Checkout\DocumentV2\Template\PaginationCounter;
use Shopware\Core\Checkout\DocumentV2\Template\TemplateContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Renders the HTML representation of a document via {@see DocumentTemplateRenderer}.
 *
 * The output doubles as the {@see PdfRenderer} Dompdf input, so browser-only styling
 * is scoped to `media="screen"` in the templates.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class HtmlRenderer extends AbstractDocumentRenderer
{
    final public const FORMAT = DocumentFormat::HTML;

    private const TEMPLATE_PATTERN = '@Framework/documents/%s.html.twig';

    public function __construct(
        private DocumentTemplateRenderer $documentTemplateRenderer,
    ) {
    }

    public function getFormat(): string
    {
        return self::FORMAT->value;
    }

    public function getDocumentTypes(): array
    {
        return [
            DocumentType::INVOICE->value,
        ];
    }

    public function renderToString(RenderInput $input, RenderState $state, Context $context): RenderResult
    {
        $renderData = $input->requireData(
            $input->documentType,
            AbstractRenderData::class,
        );

        $configuration = new TemplateContext($renderData);

        if (\preg_match('/^[a-z0-9_]+$/D', $input->documentType) !== 1) {
            throw DocumentV2Exception::invalidDocumentType($input->documentType);
        }

        $template = \sprintf(self::TEMPLATE_PATTERN, $input->documentType);

        $content = $this->documentTemplateRenderer->render(
            $template,
            $input,
            $context,
            [
                'config' => $configuration,
                'counter' => new PaginationCounter(),
            ],
        );

        $fileStem = $renderData->config->buildFileStem($renderData->documentNumber);

        return new RenderResult(
            self::FORMAT->value,
            $content,
            $fileStem,
            self::FORMAT->fileExtension(),
            self::FORMAT->mimeType(),
        );
    }
}
