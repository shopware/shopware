---
title: Fix state count bug in the flow-builder
issue:
author: Daniel Wolf
author_email: daniel.wolf@8mylez.com
author_github: supus
---
# Administration
* Added limit for state-criteria in `module/sw-flow/page/sw-flow-detail/index.js` and `module/sw-flow/component/modals/sw-flow-set-order-state-modal/index.js` to prevent missing states when total count is more than 25
