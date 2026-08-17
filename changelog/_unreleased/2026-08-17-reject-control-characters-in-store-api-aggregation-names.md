---
title: Store API aggregation names reject control characters
issue: #304
---
# Core
* Changed Store API aggregation names so they can no longer contain control characters. Invalid names are rejected before the aggregation query is built. Integrations must use printable names for aggregations.
