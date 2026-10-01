---
title: Add flag to ignore certain fields in openapi Schema
issue: NEXT-39781
author: Patryk Tomczyk
author_email: p.tomczyk@shopware.com
author_github: @patzick
---
# API
* Added new flag via `src/Core/Framework/DataAbstractionLayer/Field/Flag/IgnoreInOpenapiSchema.php` to ignore certain fields during `src/Core/Framework/Api/ApiDefinition/Generator/OpenApi/OpenApiDefinitionSchemaBuilder.php`.
    * If you have a field like `accountType` and need different schema definitions depending on the value, e.g. `business` vs `private`, you can use this flag to ignore this field in the SchemaBuilder.
    * Remember, however, that you must then add the definition via the schema json files. You can find an example here: `src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/components/schemas/Customer.json`.
