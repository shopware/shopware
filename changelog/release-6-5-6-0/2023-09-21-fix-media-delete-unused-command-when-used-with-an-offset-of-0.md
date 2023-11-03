---
title: Fix media:delete-unused command when used with an offset of 0
issue: NEXT-30627
---
# Core
* Changed `media:delete-unused` command to correctly process an offset of 0. It now executes one batch instead of treating 0 as no offset and running all batches.
