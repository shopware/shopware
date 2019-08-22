<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderBodyContextEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderFooterContextEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderHeaderContextEvent;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Twig\StringTemplateRenderer;
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

    public function renderHeader(ProductExportEntity $productExportEntity): string
    {
        if ($productExportEntity->getHeaderTemplate() === null) {
            return '';
        }

        $headerContext = $this->eventDispatcher->dispatch(
            new ProductExportRenderHeaderContextEvent(
                [
                    'productExport' => $productExportEntity,
                ]
            )
        );

        return $this->templateRenderer->render(
            $productExportEntity->getHeaderTemplate(),
            $headerContext->getContext()
        ) . PHP_EOL;
    }

    public function renderFooter(ProductExportEntity $productExportEntity): string
    {
        if ($productExportEntity->getFooterTemplate() === null) {
            return '';
        }

        $footerContext = $this->eventDispatcher->dispatch(
            new ProductExportRenderFooterContextEvent(
                [
                    'productExport' => $productExportEntity,
                ]
            )
        );

        return $this->templateRenderer->render(
            $productExportEntity->getFooterTemplate(),
            $footerContext->getContext()
        ) . PHP_EOL;
    }

    public function renderBody(ProductExportEntity $productExportEntity, EntityCollection $productCollection): string
    {
        $body = '';

        foreach ($productCollection as $productEntity) {
            $body .= $this->renderProduct($productExportEntity, $productEntity);
        }

        return $body;
    }

    public function renderProduct(
        ProductExportEntity $productExportEntity,
        SalesChannelProductEntity $productEntity
    ): string {
        $productContext = $this->eventDispatcher->dispatch(
            new ProductExportRenderBodyContextEvent(
                [
                    'product' => $productEntity,
                    'productExport' => $productExportEntity,
                ]
            )
        );

        return $this->templateRenderer->render(
                $productExportEntity->getBodyTemplate(),
                $productContext->getContext()
            ) . PHP_EOL;
    }
}
