---
title: Add indexer skip support to sync api and indexer api call
issue: NEXT-16068
---
# API
* Changed `/api/_action/sync` to accept http header `indexing-skip` to skip specific indexers in this request
* Changed `/api/_action/indexing` to accept http header `indexing-skip` to skip specific indexers in this request
