---
title:              Fix possible error on decrement message queue size
issue:              NEXT-11575
author:             Christoph Pötz
author_email:       christoph.poetz@acris.at
author_github:      @acris-cp
---
# Core
* Changed the sql statement for updating message queue stats for preventing errors on decrement message queue size smaller then 0
