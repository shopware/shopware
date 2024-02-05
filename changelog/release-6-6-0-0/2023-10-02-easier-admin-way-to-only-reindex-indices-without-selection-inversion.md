---
title: Add easier way to admin to only reindex some indices without inversion of selection
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
issue: NEXT-31920
---
# Core
* Added `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\FullEntityIndexerMessage`, that will be handled as `dal:refresh:index`
* Changed `api.action.cache.index` route to dispatch `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\FullEntityIndexerMessage` instead of `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage`
___
# API
* Added body parameter `only` to `api/_action/index` to reduce complexity when using `skip` instead
* Changed `api/_action/index` to dispatch a single message instead of a message per indexer
___
# Administration
* Added mode selection in `sw-settings-cache-index` to either skip indices like before or only run selected indexers and updaters
