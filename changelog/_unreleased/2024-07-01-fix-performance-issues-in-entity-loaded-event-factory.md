---
title: Fix performance issues in `EntityLoadedEventFactory`
issue: NEXT-00000
author: Cedric Engler
author_email: cedric.engler@pickware.de
author_github: @Ceddy610
---
# Core
Enhanced `EntityLoadedEventFactory` performance by using mapping references instead of creating new arrays, reducing memory usage and improving efficiency.

