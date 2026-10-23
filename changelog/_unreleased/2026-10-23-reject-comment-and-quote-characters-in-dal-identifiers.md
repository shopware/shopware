---
title: Reject comment and quote characters in DAL identifiers and JSON paths
issue: https://github.com/shopware/shopware-private/issues/501
---

# Core

* Rejected single and double quotes, semicolons, backslashes, the double-dash and `/* */` SQL comment markers, and control characters in DAL identifiers (aggregation names and range aggregation keys), extending the existing backtick, question mark, and colon checks.
  A lone slash or star stays allowed, because an SQL comment marker is always two characters.
  Store API and Admin API requests using a forbidden character now receive a `FRAMEWORK__INVALID_AGGREGATION_QUERY` (HTTP 400).
* Rejected the `/* */` SQL comment markers and control characters in JSON field access paths used in filters, sortings, and aggregations.
  The request is rejected with an `InvalidArgumentException` (HTTP 500), which still blocks the request and leaks no data.
