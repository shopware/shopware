---
title: Fix export of entities without default fields
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed condition for using a default sorting on `createdAt` in `\Shopware\Core\Content\ImportExport\ImportExport::export` from "has no sorting yet" to "has no sorting yet and has createdAt in its definition" to ensure, that sorting by `createdAt` will work
* Added new default sorting by `autoIncrement` to `\Shopware\Core\Content\ImportExport\ImportExport::export` as this is a sorting preferred identifier as it is reliably sortable with new entries and is unique
