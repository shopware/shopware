---
title: Add product documents
issue: NEXT-00000
author: kyle
---
# Core
* Added new entity `product_document` to attach document media (e.g. datasheets, manuals, certificates) to products, including migration `Shopware\Core\Migration\V6_7\Migration1779783880AddProductDocument`.
* Added association `product.productDocuments` (inherited) — variants without own documents inherit the documents of their parent product.
* Added private media default folder `Product documents` (entity `product_document`). Private media inside this folder is readable in sales channel scope, analogous to product download media.
* Added `Shopware\Core\Content\Product\SalesChannel\Document\AbstractProductDocumentDownloadRoute` and `ProductDocumentDownloadRoute` for downloading product documents via the Store API.
* Added `Shopware\Core\Content\Product\ProductException::productDocumentNotFound`.
___
# API
* Added Store API route `GET /store-api/product/{productId}/document/{documentId}/download`. The route enforces the sales channel visibility and active state of the product.
* Added Admin API CRUD support for the `product_document` entity via the generic entity endpoints.
___
# Storefront
* Added route `frontend.product.document.download` (`GET /product/{productId}/document/{documentId}/download`) handled by `Shopware\Storefront\Controller\ProductDocumentController`, delegating to the Store API download route.
