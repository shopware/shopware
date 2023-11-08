---
title: Dont trigger flow when importing csv
issue: NEXT-20198
---
# Core
* Changed `\Shopware\Core\Content\ImportExport\ImportExport::import` to add context's state `skipTriggerFlow` to stop trigger flow events when importing entities from import export module
