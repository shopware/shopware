---
title: Fix credit note document can't preview in order detail
issue: NEXT-23119
---
# Core
* Changed `Shopware\Core\Checkout\Document\DocumentService::preview` to update the SQL to get invoice order version.
