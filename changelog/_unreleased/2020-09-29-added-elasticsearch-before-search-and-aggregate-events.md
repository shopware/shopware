---
title: Added Elasticsearch before search and aggregate events
author: Jochen Manz
author_email: jochen.manz@gmx.de 
author_github: jochenmanz
---
# Core
* Added new `\Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntitySearcherBeforeSearchEvent` to change the elasticsearch criteria before creating the actual search object
* Added new `\Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntityAggregatorBeforeSearchEvent`  to change the elasticsearch criteria before creating the actual search object
