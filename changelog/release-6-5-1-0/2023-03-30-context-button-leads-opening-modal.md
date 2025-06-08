---
title: Context button leads opening modal
issue: NEXT-25360
---
# Administration
* Changed `moveAction` method in `module/sw-flow/component/sw-flow-sequence-action/index.js` to adjust the contextButton ref if moving the action.
* Added parameter of moveAction in  `module/sw-flow/component/sw-flow-sequence-action/sw-flow-sequence-action.html.twig` to pass the index to moveAction function.
