---
title: sw-time-ago should handle future dates as well
issue: NEXT-25236
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com
author_github: @lernhart
---
# Administration
* Changed `sw-time-ago` to handle future dates as well
* Fixed a bug where the `sw-time-ago` component would not update the time string properly, when updated via setInterval automatically
