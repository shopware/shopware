---
title: Change `sw-url-field` component to support valid URLs
issue: NEXT-15747
author: Raoul Kramer
author_email: r.kramer@shopware.com 
author_github: @djpogo
---
# Administration
* Changed input handling of URLs in `sw-url-field` to support valid URLs
* Added boolean `omitUrlHash` property when set, URL hashes `#hash` are removed
* Added boolean `omitUrlSearch` property when set, URL search parameters `?search=param` are removed
