---
title: Reject comment and quote characters in DAL identifiers and JSON paths
issue: https://github.com/shopware/shopware-private/issues/501
---

# Core

* Changed `EntityDefinitionQueryHelper::escape()` to additionally reject single and double quotes, semicolons, backslashes, the double-dash and `/* */` SQL comment markers, and control characters in DAL identifiers (aggregation names and range aggregation keys), extending the existing backtick, question mark, and colon checks.
  Store API and Admin API requests using a forbidden character now receive a `FRAMEWORK__INVALID_AGGREGATION_QUERY` (HTTP 400).
* Changed `JsonFieldAccessorBuilder` to reject the `/* */` SQL comment markers and control characters in JSON field access paths used in filters, sortings, and aggregations.
  The request is rejected with an `InvalidArgumentException` (HTTP 500), which still blocks the request and leaks no data.
* Added `DataAbstractionLayerException::invalidIdentifier()` with error code `FRAMEWORK__DBAL_INVALID_IDENTIFIER`. With the `v6.8.0.0` feature flag active it is thrown for invalid JSON field access paths as HTTP 400 instead of the `InvalidArgumentException`.
