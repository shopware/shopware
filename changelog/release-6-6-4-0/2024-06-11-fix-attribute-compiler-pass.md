---
title: Fix attribute compiler pass
issue: NEXT-36668
author: Oliver Skroblin
author_github: OliverSkroblin
---

# Core
* Changed `AttributeEntityCompilerPass` to be registered `BEFORE_REMOVING` instead of `BEFORE_OPTIMIZATION` to ensure that the attribute compiler pass is executed also for `AutoConfigureTag` attributes.
