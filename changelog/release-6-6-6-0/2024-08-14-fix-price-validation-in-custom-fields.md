---
title: Fix price validation in custom fields
issue: NEXT-27410
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer::validateMapping()` to properly validate all sub-classes of JSON-Fields, thus preventing that invalid data, which will lead to errors on read, can be saved.
