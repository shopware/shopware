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
class ZugferdEmbeddedCreditNoteRenderer extends AbstractDocumentRenderer
{
    public const TYPE = 'zugferd_embedded_credit_note';

    /**
     * @internal
     */
    public function __construct(
        protected AbstractDocumentRenderer $creditNoteRenderer,
        protected AbstractDocumentRenderer $zugferdCreditNoteRenderer,
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
        $creditNote = $this->creditNoteRenderer->render(
            $operations,
            $context,
            $rendererConfig
        );

        return $this->zugferdEmbeddedService->embed(
            $operations,
            $context,
            $rendererConfig,
            $creditNote,
            $this->zugferdCreditNoteRenderer,
            $this->shopwareVersion,
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
            $this->zugferdCreditNoteRenderer,
            $this->shopwareVersion
        );

        $orderError = $embeddedResult->getOrderError($orderId);

        if ($orderError) {
            throw $orderError;
        }
    }
}
