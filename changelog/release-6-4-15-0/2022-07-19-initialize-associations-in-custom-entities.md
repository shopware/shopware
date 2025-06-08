---
title: Initialize associations in custom entities
issue: NEXT-21626
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator::hydrateFields()` to initialize all associations in `ArrayEntities` with null values, thus fixing a problem where association keys of to-many-associations are not present for custom entities, if they weren't loaded explicitly.
