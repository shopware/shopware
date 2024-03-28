---
title: Improve hydrate performance
issue: NEXT-00000
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Core
* Added `MediaHydrator` to perform media hydration faster
* Changed `MediaDefinition` to include the new `MediaHydrator` class
* Changed the `translate` function in `EntityHydrator` to remove duplicate field value serialization
