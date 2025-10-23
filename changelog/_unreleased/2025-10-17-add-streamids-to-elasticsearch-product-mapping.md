---
title: Add streamIds field to Elasticsearch product mapping
issue: GITHUB-13151
flag: skip-trigger-flow
author: Timeo Schmidt
author_email: timeo.schmidt@villa-schmidt.de
author_github: timeo-schmidt
---

# Core

-   Added missing `streamIds` field to Elasticsearch product mapping in `ElasticsearchProductDefinition`
-   Product stream filtering now works correctly when Elasticsearch is enabled
-   The `streamIds` field is now indexed as `KEYWORD_FIELD` consistent with other ID array fields (`categoryIds`, `propertyIds`, `optionIds`, `tagIds`)

---

# API

-   Product listings can now be filtered by `streamIds` when using Elasticsearch
-   Store API queries with `streamIds` filters now return correct results instead of 0 products

---

# Administration

-   No changes

---

# Storefront

-   No changes

---

# Upgrade Information

## Elasticsearch Reindexing Required

After upgrading, shops using Elasticsearch need to reindex products to add the new `streamIds` field:

```bash
php bin/console es:mapping:update
php bin/console es:index
```

This fix resolves an issue where product stream (dynamic product group) filters would return 0 results when Elasticsearch was enabled, even though products were correctly associated with streams in the database.

---

# Next Major Version Changes

None
