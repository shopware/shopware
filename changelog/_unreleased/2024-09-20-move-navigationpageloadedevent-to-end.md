---
title: Move NavigationPageLoadedEvent to end
issue: NEXT-000000
author: Niklas Wolf
author_email: wolfniklas94@web.de
author_github: @niklaswolf
---
# Core
* Changed `NavigationPageLoader` to dispatch the `NavigationPageLoadedEvent` after setting the canonical URL, such that the canonical can be changed via an event-subscriber
