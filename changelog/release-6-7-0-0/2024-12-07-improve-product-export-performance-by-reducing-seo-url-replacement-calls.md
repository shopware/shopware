---
title: Improve product export performance by reducing seo-url replacement calls
issue: NEXT-00000
author: Felix Schneider
author_email: felix@wirduzen.de
author_github: @schneider-felix
---
# Core
* Changed `ProductExportGenerator` to not replace seo-urls for each product individually but instead replace all seo-urls at once.
