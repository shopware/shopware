---
title: Improve admin search indexing event handling and iterator versioning
issue: 11895
---
# Core
- Added `getParentDefinitionClass()` to `Shopware\Core\Checkout\Document\DocumentDefinition` to declare the parent entity (`OrderDefinition`).
- Changed `Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory::createIterator` with an optional `$versionId` parameter and applied a version filter for version-aware entities.
- Added optional `toRemoveIds` to `Shopware\Elasticsearch\Admin\AdminSearchIndexingMessage` and a getter for it.
- Changed `Shopware\Elasticsearch\Admin\AdminSearchRegistry` to:
  - Index only when the context version is the live version.
  - Use `AbstractAdminIndexer::getUpdatedIds()` to minimize indexing scope and handle deletions via `toRemoveIds`.
  - Invoke indexing directly for non Sales Channel API sources; otherwise dispatch to queue.
- Added `getUpdatedIds` method across admin indexers to react only to relevant property changes.
- Changed `OrderAdminSearchIndexer::getIterator` to restrict to only live version orders via the new iterator parameter.
