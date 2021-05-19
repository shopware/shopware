---
title: Improve the performance of calculating the exact total count of a query
author: Maximilian Ruesch
author_email: maximilian.ruesch@pickware.de
---
# Core
* Uses separate `COUNT()` query to calculate the exact `total` of an entity search instead of using
  `SQL_CALC_FOUND_ROWS` and an additional `FOUND_ROWS()` query. This improves performance of calculating the total of
  large query results.
