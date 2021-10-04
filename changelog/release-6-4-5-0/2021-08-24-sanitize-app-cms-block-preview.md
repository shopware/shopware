---
title: Sanitize App CMS block preview
issue: NEXT-16770
---
# Core
* Changed `Shopware\Core\Framework\App\Lifecycle\Persister\CmsBlockPersister::updateCmsBlocks()` to sanitize HTML preview of CMS block before writing it to the database
