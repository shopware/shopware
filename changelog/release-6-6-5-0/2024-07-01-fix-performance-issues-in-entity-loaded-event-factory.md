---
title: Fix performance issues in `EntityLoadedEventFactory`
issue: NEXT-00000
author: Cedric Engler
author_email: cedric.engler@pickware.de
author_github: @Ceddy610
---
# Core
* Changed `EntityLoadedEventFactory` to use mapping references instead of creating new arrays, reducing memory usage and improving performance.

