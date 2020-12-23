---
title: Added possibility to call the API without a version
issue: NEXT-12081
---
# API

* Added new route `/api/_info/version` or `/api/v1/_info/version` to identify the current used Shopware version 
* Added fallback api routes without versioning for all api routes to prepare next major update
    * Example: `/api/v3/product` can be now called also as `/api/product`
