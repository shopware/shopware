---
title: Improve the performance of calculating the exact total count of a query
issue: NEXT-15399
author: Maximilian Ruesch
author_email: maximilian.ruesch@pickware.de
---
# Core
* Added separate `COUNT()` query to calculate the exact `total` of an entity search instead of using
  `SQL_CALC_FOUND_ROWS` and an additional `FOUND_ROWS()` query. This improves performance of calculating the total of
  large query results.
