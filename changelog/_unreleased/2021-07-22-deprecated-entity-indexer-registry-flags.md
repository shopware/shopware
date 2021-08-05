---
title: Deprecated entity indexer registry flags
issue: NEXT-16301
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Deprecated `$context->addExtension(EntityIndexerRegistry::USE_INDEXING_QUEUE, ...)` will be ignored, use `context->addState(EntityIndexerRegistry::USE_INDEXING_QUEUE)` instead
* Deprecated `$context->addExtension(EntityIndexerRegistry::DISABLE_INDEXING, ...)` will be ignored, use `context->addState(EntityIndexerRegistry::DISABLE_INDEXING)` instead
