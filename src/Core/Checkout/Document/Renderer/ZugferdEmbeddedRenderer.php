<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Checkout\Document\Service\ZugferdEmbeddedService;
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
        $invoice = $this->invoiceRenderer->render($operations, $context, $rendererConfig);

        return $this->zugferdEmbeddedService->embed(
            $operations,
            $context,
            $rendererConfig,
            $invoice,
            $this->electronicRenderer,
            $this->shopwareVersion
        );
    }
}
