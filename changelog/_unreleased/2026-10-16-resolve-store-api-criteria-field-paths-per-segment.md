---
title: Resolve Store API criteria field paths per path segment
---
# Core
* Changed field-path resolution in `Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper` to remove whole `extensions` path segments instead of the `extensions.` substring. Field-access validation and SQL construction now interpret an accessor the same way, so a segment that only contains the text `extensions` is treated literally and rejected when it is not a real field. Valid field paths, including real DAL extension paths, are unaffected.
