---
title: Fix media folder configuration indexing
issue: NEXT-16595
---
# Core
* Changed `\Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderIndexer` to use correct entity repository and to not rely on ordering of folder ids in IndexingMessage
