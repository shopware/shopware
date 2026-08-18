<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\Service\ZugferdEmbeddedService;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @deprecated tag:v6.9.0 reason:remove-decorator - Will be removed. Use {@link DocumentGenerator} instead.
 */
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
}
