---
title: Fix exception thrown by the logic of the ElasticsearchIndexer
issue: NEXT-00000
author: Tommy Meier
author_github: @tyurderi
---
# Core
* Changed method `handleIndexingMessage` in `src/Elasticsearch/Framework/Indexing/ElasticsearchIndexer.php` to fix a thrown exception