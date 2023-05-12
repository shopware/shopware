---
title: Prevent Elasticsearch indexer from creating multiple empty indices
issue: NEXT-26212
author: Tomasz Nowicki
author_email: nowik18@gmail.com
author_github: Tomasz18
---
# Core
* Changed the `Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer::updateIds` function to fix indices creation for each function call when there are only sales channels without the default language
