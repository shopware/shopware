<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\AbstractDocumentRenderer;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\RenderInput;
use Shopware\Core\Checkout\DocumentV2\RenderResult;
use Shopware\Core\Checkout\DocumentV2\RenderState;
use Shopware\Core\Checkout\DocumentV2\Struct\InvoiceRenderData;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Error\LoaderError;

/**
 * @internal
 */
#[Package('after-sales')]
class HtmlRenderer extends AbstractDocumentRenderer
{
    public const FORMAT = DocumentFormat::Html->value;

    public function __construct(
        private readonly TemplateFinder $templateFinder,
        private readonly Environment $twig,
    ) {
    }

    public function getDocumentTypes(): array
    {
        return [
            DocumentType::Invoice->value,
            DocumentType::CancellationInvoice->value,
            DocumentType::CreditNote->value,
            DocumentType::DeliveryNote->value,
        ];
    }

    public function getFormat(): string
    {
        return self::FORMAT;
    }

    public function enrichOrderCriteria(string $docType, Criteria $criteria): void
    {
        if ($docType === DocumentType::CreditNote->value) {
            // do something different
        }

        $criteria->addAssociation('lineItems');
    }

    public function renderToString(RenderInput $renderInput, RenderState $renderState): RenderResult
    {
        // todo: do we need extra validation here or can we load arbitrary templates?
        $template = '@Framework/documentsV2/' . $renderInput->docType . '.html.twig';

        // access to typed invoice data is possible:
        /*
        $invoiceData = $renderInput->getInput('invoice');
        if (!$invoiceData instanceof InvoiceRenderData) {
            // todo: error handling
            throw new \InvalidArgumentException('Missing invoice data');
        }
        $invoiceData->intraCommunityDelivery
        */

        $parameters = $renderInput->jsonSerialize();

        $view = $this->resolveView($template);

        $rendered = $this->twig->render($view, $parameters);

        return new RenderResult($rendered);
    }

    public function persistToFile(RenderInput $renderInput, RenderResult $renderResult): string
    {
        // TODO: Implement persistToFile() method.
        return 'uuid-of-media-entity';
    }

    /**
     * @throws LoaderError
     */
    private function resolveView(string $view): string
    {
        $this->templateFinder->reset();

        return $this->templateFinder->find($view);
    }
}
