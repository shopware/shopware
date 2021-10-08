---
title: Creating the new tab "General" for orders in the order module
issue: NEXT-16673
flag: FEATURE_NEXT_7530
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com 
author_github: @lernhart
---
# Administration
* Added `alwaysShowPlaceholder` prop to `src/app/component/form/select/base/sw-select-selection-list` to always show placeholder next to the selection results.
* Added `stateType` prop to `src/module/sw-order/component/sw-order-state-select-v2` to allow it to be called more modular, instead of hardcoding state-types.
* Added component `sw-order-general-info`, which shows some summarizing information on top of the new order edit page.
