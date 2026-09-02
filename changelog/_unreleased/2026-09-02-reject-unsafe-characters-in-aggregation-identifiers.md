---
title: Reject unsafe characters in aggregation identifiers
---
# Core
* Changed aggregation validation to reject backticks, question marks, colons, and control characters in aggregation names and range aggregation keys. Store API requests using one of them now receive a `FRAMEWORK__INVALID_AGGREGATION_QUERY` (HTTP 400).
