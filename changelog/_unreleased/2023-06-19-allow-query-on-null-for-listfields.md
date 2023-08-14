---
title: Allow to query/filter on null on fields of type ListField
issue: NEXT-29895
author: Kurt Inge Smådal
author_email: kurt@flowretail.no
author_github: @kurtinge
---
# Core
* Changed DAL filtering on ListField with `NULL` value to use `IS NULL` on SQL.  
