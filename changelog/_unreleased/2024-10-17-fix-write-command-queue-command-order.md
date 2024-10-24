---
title: Fix WriteCommandQueue command order
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `getCommandsInOrder` in `WriteCommandQueue` to always execute an object's `UpdateCommand` after the `InsertCommand` of the same object
