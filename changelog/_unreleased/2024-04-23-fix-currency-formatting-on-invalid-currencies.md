---
title: Fix currency formatting on invalid currencies
issue: NEXT-15307
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added error catching for invalid currencies in the `currency` format utility and added a fallback to a decimal format.
