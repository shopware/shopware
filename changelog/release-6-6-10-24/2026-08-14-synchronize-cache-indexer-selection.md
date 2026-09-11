---
title: Cache indexer selection uses registered indexers
issue: 18673
---
# API
* Added an `indexers` map to `GET /api/_action/cache_info` with registered normal-refresh indexers and their optional child updaters. Post-update-only indexers are excluded.
___
# Administration
* Changed cache index selection to use the indexers returned by the cache-info API. Child updaters can only be selected in skip mode; only mode sends parent indexers exclusively.
