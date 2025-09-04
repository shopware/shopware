---
title: Skip MediaIndexing when remote thumbnails are enabled
---
# Core
* Changed `\Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexer` to not dispatch MediaIndexingMessages, when remote thumbnails are enabled. The handle method already did an early return, but in that case it is not needed to generate the messages and route them through the queue. 
