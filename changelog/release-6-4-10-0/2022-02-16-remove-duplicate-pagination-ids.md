---
title: Removing duplicate DOM IDs from pagination
issue: NEXT-20191
author: SkaveRat
author_email: github@skaverat.net
author_github: SkaveRat
---
# Storefront
* Changed `Resources/views/storefront/component/pagination.html.twig` and added variable `paginationSuffix` to use a suffix when generating DOM IDs, to prevent duplicate IDs
