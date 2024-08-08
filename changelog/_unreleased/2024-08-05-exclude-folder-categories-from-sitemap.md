---
title: Exclude folder categories from sitemap
issue: NEXT-37588
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Core
* Changed method `getCategories()` in `src/Core/Content/Sitemap/Provider/CategoryUrlProvider.php` to exclude categories of type `folder`.
