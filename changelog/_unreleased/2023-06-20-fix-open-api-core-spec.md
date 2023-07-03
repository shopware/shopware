---
title: Add missing OpenApi definitions
issue: NEXT-28688
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Core
* Add the missing description definition for the "error" schema in the OpenApiSchemaBuilder
* Add the missing sections in the OpenApi-Spec by including all components, not just the schemas in the OpenApiFileLoader
* Add the missing type definitions for schemas and properties in the OpenApiDefinitionSchemaBuilder
* Add the missing type & pattern definitions to StoreApi path properties in the json files
