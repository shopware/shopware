<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Service\ZugferdEmbeddedService;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('after-sales')]
class ZugferdEmbeddedCancellationInvoiceRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'zugferd_embedded_cancellation_invoice';

    /**
     * @internal
     */
    public function __construct(
        protected AbstractDocumentRenderer $cancellationInvoiceRenderer,
        protected AbstractDocumentRenderer $zugferdCancellationInvoiceRenderer,
        protected ZugferdEmbeddedService $zugferdEmbeddedService,
        protected string $shopwareVersion,
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
        $cancellationInvoice = $this->cancellationInvoiceRenderer->render(
            $operations,
            $context,
            $rendererConfig
        );

        return $this->zugferdEmbeddedService->embed(
            $operations,
            $context,
            $rendererConfig,
            $cancellationInvoice,
            $this->zugferdCancellationInvoiceRenderer,
            $this->shopwareVersion
        );
    }

    /**
     * @throws \Throwable
     *
     * @deprecated tag:v6.7.0 - will be removed without replacement
     */
    public function finalize(DocumentGenerateOperation $operation, Context $context, DocumentRendererConfig $rendererConfig, RendererResult $result): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Method will be removed without replacement');

        $orderId = $operation->getOrderId();
        $successDocument = $result->getOrderSuccess($orderId);

        if (!$successDocument) {
            throw DocumentException::generationError('Success document not found');
        }

        $embeddedResult = $this->zugferdEmbeddedService->embed(
            [$orderId => $operation],
            $context,
            $rendererConfig,
            $result,
            $this->zugferdCancellationInvoiceRenderer,
            $this->shopwareVersion
        );

        $orderError = $embeddedResult->getOrderError($orderId);

        if ($orderError) {
            throw $orderError;
        }
    }
}
