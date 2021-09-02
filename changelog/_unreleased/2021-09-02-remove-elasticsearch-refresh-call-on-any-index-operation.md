---
title: Remove Elasticsearch refresh call on any index operation
issue: NEXT-16757
---
# Core
* Removed Elasticsearch refresh operation on any indexing operation as the Elasticsearch server does itself with the default `index.refresh_interval` setting. This is in default set to 1 seconds
