<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Twig\StringTemplateRenderer;

class ProductExportRenderService implements ProductExportRenderServiceInterface
{
    /** @var StringTemplateRenderer */
    private $templateRenderer;

    public function __construct(StringTemplateRenderer $templateRenderer)
    {
        $this->templateRenderer = $templateRenderer;
    }

    public function renderHeader(ProductExportEntity $productExportEntity): string
    {
        if ($productExportEntity->getHeaderTemplate() === null) {
            return '';
        }

        return $this->templateRenderer->render(
            $productExportEntity->getHeaderTemplate(),
            [
                'productExport' => $productExportEntity,
            ]
        ) . PHP_EOL;
    }

    public function renderFooter(ProductExportEntity $productExportEntity): string
    {
        if ($productExportEntity->getFooterTemplate() === null) {
            return '';
        }

        return $this->templateRenderer->render(
            $productExportEntity->getFooterTemplate(),
            [
                'productExport' => $productExportEntity,
            ]
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

    public function renderProduct(ProductExportEntity $productExportEntity, SalesChannelProductEntity $productEntity): string
    {
        return $this->templateRenderer->render(
            $productExportEntity->getBodyTemplate(),
            [
                'product' => $productEntity,
                'productExport' => $productExportEntity,
            ]
        ) . PHP_EOL;
    }
}
