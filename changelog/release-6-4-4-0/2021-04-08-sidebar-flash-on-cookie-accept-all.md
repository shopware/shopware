---
title: Sidebar flash on cookie accept all
issue: NEXT-16516
author: Rune Laenen
author_email: rune@laenen.me 
author_github: runelaenen
---
# Storefront
* Changed `cookie-configuration.plugin.js` to not flash the cookie sidebar when Accept All is clicked from the cookie bar.
* Added `loadIntoMemory` to the `acceptAllCookies` method. If passed true, the off canvas menu will not be opened, and the DOM will be loaded into memory.
