---
title: Fixed the use of the configured currency in the product export
issue: NEXT-18736
author: Martin Krzykawski
author_email: m.krzykawski@shopware.com
---
# Core
* Changed the used currency id in `Shopware\Core\Content\ProductExport\Service\ProductExportGenerator::generate` to ensure that the configured currency of the product export will be used and not of the storefront sales channel domain
