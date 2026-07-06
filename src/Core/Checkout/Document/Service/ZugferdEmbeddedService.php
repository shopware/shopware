<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use horstoeko\zugferd\ZugferdDocumentPdfMerger;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final class ZugferdEmbeddedService
{
    final public const SHOPWARE_ZUGFERD_CREATOR_TAG_PREFIX = 'Shopware@';

    private const MISSING_ELECTRONIC_DOCUMENT_ERROR = 'Zugferd document is null';

    private const INCORRECT_CONTENT_TYPE_ERROR = 'Content type must be "application/pdf"';

    private const PDF_GENERATION_ERROR = 'Error during PDF generation';

    /**
     * @param array<string, DocumentGenerateOperation> $operations
     */
    public function embed(
        array $operations,
        Context $context,
        DocumentRendererConfig $rendererConfig,
        RendererResult $baseDocument,
        AbstractDocumentRenderer $zugferdRenderer,
        string $shopwareVersion,
    ): RendererResult {
        $this->setSuccessDocumentNumbers($baseDocument->getSuccess(), $operations);

        $renderResult = new RendererResult();
        $electronicDocument = $zugferdRenderer->render(
            $operations,
            $context,
            $rendererConfig
        );

        foreach ($baseDocument->getSuccess() as $orderId => $pdfDocument) {
            if ($pdfDocument->getContentType() !== FileTypes::PDF_CONTENT_TYPE) {
                $renderResult->addError($orderId, DocumentException::electronicInvoiceViolation(
                    1,
                    [self::INCORRECT_CONTENT_TYPE_ERROR => [$orderId]]
                ));

                continue;
            }

            $electronicDoc = $electronicDocument->getOrderSuccess($orderId);

            if ($electronicDoc === null) {
                $renderResult->addError($orderId, DocumentException::electronicInvoiceViolation(
                    1,
                    [self::MISSING_ELECTRONIC_DOCUMENT_ERROR => [$orderId]]
                ));

                continue;
            }

            try {
                $combined = $this->merge(
                    $electronicDoc->getContent(),
                    $pdfDocument->getContent(),
                    $shopwareVersion
                );

                $pdfDocument->setName('embedded_' . $pdfDocument->getName());
                $pdfDocument->setContent($combined);

                $renderResult->addSuccess($orderId, $pdfDocument);
            } catch (\Throwable $exception) {
                $renderResult->addError($orderId, DocumentException::electronicInvoiceViolation(
                    1,
                    [self::PDF_GENERATION_ERROR . ': ' . $exception->getMessage() => [$orderId]]
                ));
            }
        }

        $renderResult->assign([
            'errors' => \array_merge(
                $baseDocument->getErrors(),
                $electronicDocument->getErrors(),
                $renderResult->getErrors()
            ),
        ]);

        return $renderResult;
    }

    /**
     * @param array<string, RenderedDocument> $successes
     * @param array<string, DocumentGenerateOperation> $operations
     */
    public function setSuccessDocumentNumbers(array $successes, array $operations): void
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

    private function merge(string $electronicContent, string $pdfContent, string $shopwareVersion): string
    {
        return (new ZugferdDocumentPdfMerger($electronicContent, $pdfContent))
            ->setAdditionalCreatorTool(self::SHOPWARE_ZUGFERD_CREATOR_TAG_PREFIX . $shopwareVersion)
            ->generateDocument()
            ->downloadString();
    }
}
