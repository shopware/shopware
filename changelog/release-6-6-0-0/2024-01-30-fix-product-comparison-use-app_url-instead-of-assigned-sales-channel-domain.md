---
title: Fix product comparison use APP_URL instead of assigned sales channel domain
issue: NEXT-31770
---
# Core
* Changed `\Shopware\Core\Content\ProductExport\Service\ProductExportRenderer::renderHeader` to use correct sales channel domain's url in the template when rendering product export
* Changed `\Shopware\Core\Content\ProductExport\Service\ProductExportRenderer::renderFooter` to use correct sales channel domain's url in the template when rendering product export
* Changed `\Shopware\Core\Content\ProductExport\Service\ProductExportRenderer::renderBody` to use correct sales channel domain's url in the template when rendering product export