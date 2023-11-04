<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Monolog\Level;
use Shopware\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderFooterContextEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderHeaderContextEvent;
use Shopware\Core\Content\ProductExport\Exception\RenderFooterException;
use Shopware\Core\Content\ProductExport\Exception\RenderHeaderException;
use Shopware\Core\Content\ProductExport\Exception\RenderProductException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Twig\Exception\StringTemplateRenderingException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('sales-channel')]
class ProductExportRenderer implements ProductExportRendererInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly StringTemplateRenderer $templateRenderer,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler
    ) {
    }

    public function renderHeader(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext
    ): string {
        if ($productExport->getHeaderTemplate() === null) {
            return '';
        }

        $headerContext = $this->eventDispatcher->dispatch(
            new ProductExportRenderHeaderContextEvent(
                [
                    'productExport' => $productExport,
                    'context' => $salesChannelContext,
                ]
            )
        );

        try {
            $content = $this->templateRenderer->render(
                $productExport->getHeaderTemplate(),
                $headerContext->getContext(),
                $salesChannelContext->getContext()
            ) . \PHP_EOL;

            return $this->replaceSeoUrlPlaceholder($content, $productExport, $salesChannelContext);
        } catch (StringTemplateRenderingException $exception) {
            $renderHeaderException = new RenderHeaderException($exception->getMessage());
            $this->logException($salesChannelContext->getContext(), $renderHeaderException);

            throw $renderHeaderException;
        }
    }

    public function renderFooter(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext
    ): string {
        if ($productExport->getFooterTemplate() === null) {
            return '';
        }

        $footerContext = $this->eventDispatcher->dispatch(
            new ProductExportRenderFooterContextEvent(
                [
                    'productExport' => $productExport,
                    'context' => $salesChannelContext,
                ]
            )
        );

        try {
            $content = $this->templateRenderer->render(
                $productExport->getFooterTemplate(),
                $footerContext->getContext(),
                $salesChannelContext->getContext()
            ) . \PHP_EOL;

            return $this->replaceSeoUrlPlaceholder($content, $productExport, $salesChannelContext);
        } catch (StringTemplateRenderingException $exception) {
            $renderFooterException = new RenderFooterException($exception->getMessage());
            $this->logException($salesChannelContext->getContext(), $renderFooterException);

            throw $renderFooterException;
        }
    }

    /**
     * @param array<string, mixed>               $data
     */
    public function renderBody(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext,
        array $data
    ): string {
        $bodyTemplate = $productExport->getBodyTemplate();
        if (!\is_string($bodyTemplate)) {
            throw new \RuntimeException('Product export body template is not set');
        }

        try {
            $content = $this->templateRenderer->render(
                $bodyTemplate,
                $data,
                $salesChannelContext->getContext()
            ) . \PHP_EOL;

            return $this->replaceSeoUrlPlaceholder($content, $productExport, $salesChannelContext);
        } catch (StringTemplateRenderingException $exception) {
            $renderProductException = new RenderProductException($exception->getMessage());
            $this->logException($salesChannelContext->getContext(), $renderProductException);

            throw $renderProductException;
        }
    }

    private function logException(
        Context $context,
        \Exception $exception
    ): void {
        $loggingEvent = new ProductExportLoggingEvent(
            $context,
            $exception->getMessage(),
            Level::Error,
            $exception
        );

        $this->eventDispatcher->dispatch($loggingEvent);
    }

    private function replaceSeoUrlPlaceholder(
        string $content,
        ProductExportEntity $productExportEntity,
        SalesChannelContext $salesChannelContext
    ): string {
        return $this->seoUrlPlaceholderHandler->replace(
            $content,
            $productExportEntity->getSalesChannelDomain()->getUrl(),
            $salesChannelContext
        );
    }
}
