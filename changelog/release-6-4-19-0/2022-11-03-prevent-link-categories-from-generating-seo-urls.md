---
title: Prevent link categories from generating seo urls
issue: NEXT-24127
author: Felix von WIRDUZEN
author_email: felix@wirduzen.de
author_github: wirduzen-felix
___
# Storefront
* Added additional filter to `src/Storefront/Framework/Seo/SeoUrlRoute/NavigationPageSeoUrlRoute.php::prepareCriteria()`
to prevent SeoUrl generation for link categories
