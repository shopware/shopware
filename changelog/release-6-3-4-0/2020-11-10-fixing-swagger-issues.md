---
title: Fix swagger compatibility issues in store-api
issue: NEXT-12050
---
# Core
* Changed store-api swagger to match OpenAPI spec
    * Moved custom schema under `#/components/schemas/` to match OpenAPI spec
    * Replace wrong Parameter annotation with RequestBody
    * Added missing Parameter annotation for parameters in URL
