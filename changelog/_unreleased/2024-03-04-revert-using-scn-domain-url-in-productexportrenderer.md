---
title: Revert using SCN Domain URL in ProductExportRenderer
issue: NEXT-34012
---
# Core
* Changed `\Shopware\Core\Content\ProductExport\Service\ProductExportRenderer::renderBody` to not replace the media domain URL with the Sales channel's domain URL.