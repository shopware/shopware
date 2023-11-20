---
title: Improve-ElasticSearch-Debugging
issue: NEXT-19579
author: Alexandru Dumea
author_email: a.dumea@shopware.com
author_github: Alexandru Dumea
---
# API
* Added a new configuration option to remove the _source field and ensure all fields are present in Elasticsearch (ES).

* Added an only parameter to the es:index command: `bin/console es:index --only=entityName` ,  only entities explicitly defined in this list will be indexed, providing more control over the indexing process.
