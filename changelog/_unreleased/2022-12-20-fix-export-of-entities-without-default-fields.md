---
title: Fix export of entities without default fields
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed fallback sorting on `createdAt` in `\Shopware\Core\Content\ImportExport\ImportExport::export` as `createdAt` is not indexed to any `autoIncrement` column if available as it is reliable for sorting and unique
* Changed default sorting from field `id` to every primary key field instead
