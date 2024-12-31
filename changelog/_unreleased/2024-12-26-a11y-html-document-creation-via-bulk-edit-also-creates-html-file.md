---
title: [A11y-HTML] Document creation via Bulk Edit also creates HTML File
issue: NEXT-40066
---
# Core
* Changed `Shopware\Core\Checkout\Document\Controller\DocumentController::downloadDocuments` to add the attribute `fileType` to the request.
* Changed `Shopware\Core\Checkout\Document\Service\DocumentMerger::merge` to implement a case zip archive to merge documents.
___
# Administration
*
