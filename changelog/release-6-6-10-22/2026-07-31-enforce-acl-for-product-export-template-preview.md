---
title: Template rendering endpoints require update privileges
issue: #18743
---
# API
* Changed the `POST /api/_action/product-export/preview` and `POST /api/_action/product-export/validate` endpoints to require the `product_export:update` ACL privilege. Admin API integrations and users that use these endpoints must be granted the respective existing privilege.
