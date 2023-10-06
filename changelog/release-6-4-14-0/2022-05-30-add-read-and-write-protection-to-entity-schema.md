---
title: Add read and write protection to entity schema
issue: NEXT-22321
author: Joshua Behrens
author_email: code@joshua-behrens
author_github: @JoshuaBehrens
---
# Core
* Added the keys `read-protected` and `write-protected` to entities returned from `\Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator`
___
# API
* Added the keys `read-protected` and `write-protected` to entities returned from `/api/_info/entity-schema.json` / `api.info.entity-schema`
* Removed entities returned from `/api/_info/entity-schema.json` / `api.info.entity-schema` when they are read and write protected
