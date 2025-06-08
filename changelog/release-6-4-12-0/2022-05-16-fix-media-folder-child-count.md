---
title: Fix media folder child count
issue: NEXT-20300
---
# Core
* Changed `\Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderIndexer` to also generate IndexingMessages for the media folder parent ids, thus fixing the calculation of the child count.
