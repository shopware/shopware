---
title: Reduce elasticsearch refresh amount
issue: NEXT-15812
---
# Core
* Changed refresh behavior of Elasticsearch to
    * refresh Elasticsearch once per update of an entity
    * refresh Elasticsearch index once before changing the alias
___
