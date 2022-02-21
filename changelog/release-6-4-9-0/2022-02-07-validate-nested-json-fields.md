---
title: Fix validation of nested JsonFields
issue: NEXT-19378
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer::validateMapping()` to validate nested JsonFields, even if they don't have a property mapping, thus fixing issues where inconsistent order data could be written over the API.
