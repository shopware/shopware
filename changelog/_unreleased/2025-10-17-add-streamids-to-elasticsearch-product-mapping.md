---
title: Add streamIds field to Elasticsearch product mapping
issue: GITHUB-13151
flag: skip-trigger-flow
author: Timeo Schmidt
author_email: timeo.schmidt@villa-schmidt.de
author_github: timeo-schmidt
---

# Core
* Added missing `streamIds` field to Elasticsearch product mapping in `ElasticsearchProductDefinition`
* Added `KEYWORD_FIELD` indexing for `streamIds` consistent with other ID array fields (`categoryIds`, `propertyIds`, `optionIds`, `tagIds`)

---

# API
* Added support for filtering product listings by `streamIds` when using Elasticsearch

---

# Upgrade Information

## Elasticsearch Reindexing Required

After upgrading, shops using Elasticsearch need to reindex products to add the new `streamIds` field:

```bash
php bin/console es:mapping:update
php bin/console es:index
```

This fix resolves an issue where product stream (dynamic product group) filters would return 0 results when Elasticsearch was enabled, even though products were correctly associated with streams in the database.
