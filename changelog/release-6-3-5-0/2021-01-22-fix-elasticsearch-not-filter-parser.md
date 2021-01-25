---
title: Fix the Elasticsearch Query Parser for NotFilters
issue: NEXT-13413
---
# Core
*  Changed the ElasticSearch CriteriaParser `parseNotFilter` method to fix the parsing of multi not queries. Products with Clearance sale are now searchable with ElasticSearch.
