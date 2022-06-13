---
title: Flow builder action list not sorted correctly
issue: NEXT-21781
---
# Administration
* Changed `groups` computed in `/module/sw-flow/component/sw-flow-sequence-action/index.js` to sort by Label.
* Changed `getActionTitle` method in `/module/sw-flow/component/sw-flow-sequence-action/index.js` to get correct title of action.
* Added `getStopFlowIndex` method in `/module/sw-flow/component/sw-flow-sequence-action/index.js` to get Stop Flow action index.
* Changed `sortActionOptions` method in `/module/sw-flow/component/sw-flow-sequence-action/index.js` to sort action option.
