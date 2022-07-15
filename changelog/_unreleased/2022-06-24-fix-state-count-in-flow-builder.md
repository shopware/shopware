---
title: Fix state count bug in the flow-builder
issue: NEXT-22153
author: Daniel Wolf
author_email: daniel.wolf@8mylez.com
author_github: supus
---
# Administration
* Remove limit for state-criteria in `module/sw-flow/page/sw-flow-detail/index.js` to prevent missing states when total count is more than 25
