---
title: Optimize variant indexing behavior
issue: NEXT-19766
---
# Core
* Changed `ProductIndexer::handle`. When updating a parent product, the variants are no longer updated in the same request. Instead, a message is placed in the queue in a chunk of 50 variants.