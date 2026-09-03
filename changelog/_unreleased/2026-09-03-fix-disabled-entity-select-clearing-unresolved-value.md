---
title: Fix disabled sw-entity-single-select clearing an unresolved bound value
author: Waqas Ahmed
author_github: wakqasahmed
---
# Administration
* Fixed `sw-entity-single-select` emitting `update:value` with `null` when the bound entity can't be resolved under the passed criteria while the select is disabled, which could corrupt required order fields (see #20030)
