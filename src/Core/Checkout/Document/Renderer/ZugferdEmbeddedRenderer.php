<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use horstoeko\zugferd\ZugferdDocumentPdfMerger;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('after-sales')]
class ZugferdEmbeddedRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'zugferd_embedded_invoice';

    /**
     * @internal
     */
    public function __construct(
        protected AbstractDocumentRenderer $invoiceRenderer,
        protected AbstractDocumentRenderer $electronicRenderer,
        protected string $shopwareVersion
    ) {
    }

    public function supports(): string
    {
        return self::TYPE;
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        throw new DecorationPatternException(self::class);
    }

    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        $invoice = $this->invoiceRenderer->render($operations, $context, $rendererConfig);

        if (!Feature::isActive('v6.7.0.0')) {
            return $invoice;
        }

        return $this->embedXMLIntoPDF($operations, $context, $rendererConfig, $invoice);
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed without replacement
     *
     * @param DocumentGenerateOperation[] $operations
     */
    public function finalize(array $operations, Context $context, DocumentRendererConfig $rendererConfig, RendererResult $result): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Method will be removed without replacement');

        $this->embedXMLIntoPDF($operations, $context, $rendererConfig, $result);
    }

    /**
     * @param DocumentGenerateOperation[] $operations
     */
    protected function embedXMLIntoPDF(array $operations, Context $context, DocumentRendererConfig $rendererConfig, RendererResult $invoice): RendererResult
    {
        // So ElectronicRenderer don't need to create a new number
        $this->setSuccessDocumentNumbers($invoice->getSuccess(), $operations);
        $electronicInvoice = $this->electronicRenderer->render($operations, $context, $rendererConfig);
        $renderResult = new RendererResult();

        foreach ($invoice->getSuccess() as $orderId => $invoiceDocument) {
            $electronicDoc = $electronicInvoice->getOrderSuccess($orderId);
            if ($electronicDoc === null) {
                $renderResult->addError($orderId, DocumentException::electronicInvoiceViolation(1, ['Electronic invoice is null' => [$orderId]]));

                continue;
            }

            try {
                $combined = (new ZugferdDocumentPdfMerger($electronicDoc->getContent(), $invoiceDocument->getContent()))
                    ->setAdditionalCreatorTool('Shopware@' . $this->shopwareVersion)
                    ->generateDocument()
                    ->downloadString();

                $invoiceDocument->setName('embedded_' . $invoiceDocument->getName());
                $invoiceDocument->setContent($combined);

                $renderResult->addSuccess($orderId, $invoiceDocument);
            } catch (\Throwable $e) {
                $renderResult->addError($orderId, $e);
            }
        }

        $renderResult->assign(['errors' => \array_merge($invoice->getErrors(), $electronicInvoice->getErrors(), $renderResult->getErrors())]);

        return $renderResult;
    }

    /**
     * @param array<string, RenderedDocument> $successes
     * @param DocumentGenerateOperation[] $operations
     */
    protected function setSuccessDocumentNumbers(array $successes, array $operations): void
    {
        foreach ($successes as $orderId => $document) {
            $operation = $operations[$orderId] ?? null;
            if (!$operation) {
                continue;
            }

            $config = $operation->getConfig();
            $config['documentNumber'] = $document->getNumber();
            $operation->assign(['config' => $config]);
        }
    }
}
