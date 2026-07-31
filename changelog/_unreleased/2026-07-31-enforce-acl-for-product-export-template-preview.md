---
title: Enforce ACL for product export template preview
issue: #18743
---
# API
* Changed `POST /api/_action/product-export/preview` and `POST /api/_action/product-export/validate` to require the existing `product_export:update` ACL privilege, as both endpoints render caller-provided Twig templates. Admin API integrations and users calling these endpoints must be granted this privilege.
___
# Administration
* Changed the template test and preview buttons in the sales channel product comparison view to be disabled for users without the `sales_channel.editor` privilege.
