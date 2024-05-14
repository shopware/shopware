---
title: Fix OpenApi definitions
issue: NEXT-00000
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Core
* Removed type `object` from the schema definitions of the `StoreApi` schema where `allOf` exists
* Changed `OrderRouteResponse` `orders` property to type `array`
* Changed `OpenApiDefinitionSchemaBuilder` to exclude the `required` field if it's empty
* Changed `OpenApiDefinitionSchemaBuilder` to use type `array` with `items` for `ToMany` AssociationFields
* Changed `OpenApiDefinitionSchemaBuilder` to only include the `description` field if `since` is not empty
