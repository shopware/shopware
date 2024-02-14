---
title: Product Export Renderer use cdn url when set
issue: NEXT-33642
---
# Core
* Changed `\Shopware\Core\Content\ProductExport\Service\ProductExportRenderer::renderBody` to not replace the media url with the sales channel domain url if the cdn url is set.