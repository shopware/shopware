---
title: Fix storefront theme asset paths
issue: NEXT-25537
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: Gecolay
---
# Storefront
* Changed the image asset path in `listing.html.twig` to the new `asset('...', 'theme')` structure
* Changed the image asset path in `error-404.html.twig` to the new `asset('...', 'theme')` structure
* Changed the image asset path in `error-maintenance.html.twig` to the new `asset('...', 'theme')` structure
