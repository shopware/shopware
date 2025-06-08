---
title: Fix display warning alert
issue: NEXT-18634
---
# Administration
* Removed `flowChanges` computed in `/module/sw-flow/page/sw-flow-detail/index.js`.
* Changed `beforeRouteLeave` in `/module/sw-flow/page/sw-flow-detail/index.js` hook to use new logic for checking has any change.
* Added new `originFlow` state in `/module/sw-flow/state/flow.state.js` to compare with new change data.
* Added new `hasFlowChanged` getter in `/module/sw-flow/state/flow.state.js` to detected change data.
