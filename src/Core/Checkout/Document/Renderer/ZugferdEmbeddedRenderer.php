<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use horstoeko\zugferd\ZugferdDocumentPdfMerger;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
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

        return $this->embedXMLIntoPDF($operations, $context, $rendererConfig, $invoice);
    }

    /**
     * @param array<string, DocumentGenerateOperation> $operations
     */
    private function embedXMLIntoPDF(array $operations, Context $context, DocumentRendererConfig $rendererConfig, RendererResult $invoice): RendererResult
    {
        // So ElectronicRenderer don't need to create a new number
        $this->setSuccessDocumentNumbers($invoice->getSuccess(), $operations);
        $electronicInvoice = $this->electronicRenderer->render($operations, $context, $rendererConfig);
        $renderResult = new RendererResult();

        foreach ($invoice->getSuccess() as $orderId => $invoiceDocument) {
            if ($invoiceDocument->getContentType() !== 'application/pdf') {
                $renderResult->addError($orderId, DocumentException::electronicInvoiceViolation(1, ['Application type must be "application/pdf"' => [$orderId]]));

                continue;
            }

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
     * @param array<string, DocumentGenerateOperation> $operations
     */
    private function setSuccessDocumentNumbers(array $successes, array $operations): void
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
