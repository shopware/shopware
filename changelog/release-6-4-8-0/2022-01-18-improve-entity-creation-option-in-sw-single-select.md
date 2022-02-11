---
title: Improve entity creation option in sw-single-select
issue: NEXT-18179
author: Ramona Schwering
author_email: r.schwering@shopware.com
author_github: leichteckig
---
# Administration
* Changed entity creation option in `sw-entity-single-select` to support `contains` criteria for displaying results and entity creation with substring search terms at the same time
  * Changed entity creation to happen in `search` method, similar to the implementation in `sw-entity-tag-select`
  * Removed entity creation from `loadData` method instead
* Added method to filter unnecessary placeholder entities if searchTerm is empty
