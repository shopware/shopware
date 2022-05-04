---
title: Added number range and auto increment in search result
issue: NEXT-20944
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---
# Core
* Added new `AutoIncrement` field type to identify definitions which supports an auto increment.
* Added `NumberRangeField` and `AutoIncrementField` to the `IdSearchResult` when using the `EntityRepository::searchIds` function.
* Added last id iterator logic in `RepositoryIterator` to support faster iterations.