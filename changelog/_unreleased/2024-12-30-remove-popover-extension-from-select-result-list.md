---
title: Remove popover extension from select result list
issue: NEXT-0000
author: Cedric Engler
author_email: cedric.engler@pickware.de
author_github: @Ceddy610
---
# Administration
* Changed `sw-select-result-list` to not use the popover extension anymore, to not swallow any `scroll` events on this element.
* Changed `sw-select-result-list` to round down the bottom distance of the popover container.
