---
title: Disable Prefix Search for Synonyms to Prevent Irrelevant Results
issue: NEXT-40170
---
# Core
* Changed `build` method in `Shopware\Elasticsearch\TokenQueryBuilder` to add prefix search query for original term only.
