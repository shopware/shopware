---
title: Update `MessageQueueSizeRestrictListener` to skip on `enforceLimit = false`
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed the `MessageQueueSizeRestrictListener` to directly skip all checks, if `enforceLimit` is set to `false` to skip calculation time for large messages
