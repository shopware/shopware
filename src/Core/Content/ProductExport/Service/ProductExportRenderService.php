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

class ProductExportRenderService implements ProductExportRenderServiceInterface
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
        ProductExportEntity $productExportEntity,
        SalesChannelContext $salesChannelContext
    ): string {
        if ($productExportEntity->getHeaderTemplate() === null) {
            return '';
        }

        $headerContext = $this->eventDispatcher->dispatch(
            new ProductExportRenderHeaderContextEvent(
                [
                    'productExport' => $productExportEntity,
                    'context' => $salesChannelContext,
                ]
            )
        );

        try {
            return $this->templateRenderer->render(
                    $productExportEntity->getHeaderTemplate(),
                    $headerContext->getContext()
                ) . PHP_EOL;
        } catch (StringTemplateRenderingException $exception) {
            throw new RenderHeaderException($exception->getMessage());
        }
    }

    public function renderFooter(
        ProductExportEntity $productExportEntity,
        SalesChannelContext $salesChannelContext
    ): string {
        if ($productExportEntity->getFooterTemplate() === null) {
            return '';
        }

        $footerContext = $this->eventDispatcher->dispatch(
            new ProductExportRenderFooterContextEvent(
                [
                    'productExport' => $productExportEntity,
                    'context' => $salesChannelContext,
                ]
            )
        );

        try {
            return $this->templateRenderer->render(
                    $productExportEntity->getFooterTemplate(),
                    $footerContext->getContext()
                ) . PHP_EOL;
        } catch (StringTemplateRenderingException $exception) {
            throw new RenderFooterException($exception->getMessage());
        }
    }

    public function renderBody(
        ProductExportEntity $productExportEntity,
        EntityCollection $productCollection,
        SalesChannelContext $salesChannelContext
    ): string {
        $body = '';

        foreach ($productCollection as $productEntity) {
            $body .= $this->renderProduct($productExportEntity, $productEntity, $salesChannelContext);
        }

        return $body;
    }

    public function renderProduct(
        ProductExportEntity $productExportEntity,
        SalesChannelProductEntity $productEntity,
        SalesChannelContext $salesChannelContext
    ): string {
        $productContext = $this->eventDispatcher->dispatch(
            new ProductExportRenderBodyContextEvent(
                [
                    'product' => $productEntity,
                    'productExport' => $productExportEntity,
                    'context' => $salesChannelContext,
                ]
            )
        );
        try {
            return $this->templateRenderer->render(
                    $productExportEntity->getBodyTemplate(),
                    $productContext->getContext()
                ) . PHP_EOL;
        } catch (StringTemplateRenderingException $exception) {
            throw new RenderProductException($exception->getMessage());
        }
    }
}
