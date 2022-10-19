---
title: Show correctly payment histories in order admin page
issue: NEXT-23581
---
# Administration
* Added `fetchEntries` method in `administration/src/module/sw-order/component/sw-order-state-history-card/index.js` to fetch history for each machine state.
* Changed `getStateHistoryEntries` and `buildStateHistory` methods in `administration/src/module/sw-order/component/sw-order-state-history-card/index.js` to build correctly histories.
