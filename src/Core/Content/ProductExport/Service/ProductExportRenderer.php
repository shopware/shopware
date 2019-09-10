<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderBodyContextEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderFooterContextEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderHeaderContextEvent;
use Shopware\Core\Content\ProductExport\Exception\RenderFooterException;
use Shopware\Core\Content\ProductExport\Exception\RenderHeaderException;
use Shopware\Core\Content\ProductExport\Exception\RenderProductException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Twig\Exception\StringTemplateRenderingException;
use Shopware\Core\Framework\Twig\StringTemplateRenderer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductExportRenderer implements ProductExportRendererInterface
{
    /** @var StringTemplateRenderer */
    private $templateRenderer;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    public function __construct(StringTemplateRenderer $templateRenderer, EventDispatcherInterface $eventDispatcher)
    {
        $this->templateRenderer = $templateRenderer;
        $this->eventDispatcher = $eventDispatcher;
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
            return $this->templateRenderer->render(
                    $productExport->getHeaderTemplate(),
                    $headerContext->getContext()
                ) . PHP_EOL;
        } catch (StringTemplateRenderingException $exception) {
            throw new RenderHeaderException($exception->getMessage());
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
            return $this->templateRenderer->render(
                    $productExport->getFooterTemplate(),
                    $footerContext->getContext()
                ) . PHP_EOL;
        } catch (StringTemplateRenderingException $exception) {
            throw new RenderFooterException($exception->getMessage());
        }
    }

    public function renderBody(
        ProductExportEntity $productExport,
        EntityCollection $productCollection,
        SalesChannelContext $salesChannelContext
    ): string {
        $body = '';

        foreach ($productCollection as $product) {
            $body .= $this->renderProduct($productExport, $product, $salesChannelContext);
        }

        return $body;
    }

    private function renderProduct(
        ProductExportEntity $productExport,
        SalesChannelProductEntity $product,
        SalesChannelContext $salesChannelContext
    ): string {
        $productContext = $this->eventDispatcher->dispatch(
            new ProductExportRenderBodyContextEvent(
                [
                    'product' => $product,
                    'productExport' => $productExport,
                    'context' => $salesChannelContext,
                ]
            )
        );
        try {
            return $this->templateRenderer->render(
                    $productExport->getBodyTemplate(),
                    $productContext->getContext()
                ) . PHP_EOL;
        } catch (StringTemplateRenderingException $exception) {
            throw new RenderProductException($exception->getMessage());
        }
    }
}
