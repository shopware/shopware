---
title: Fix loading of CountryStates for AccountLoginPage
issue: NEXT-19379
author: Ulrich Thomas Gabor
author_email: ulrich.thomas.gabor@odd-solutions.de
author_github: @UlrichThomasGabor
---
# Storefront
* Changed `\Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader` to remove association prefix, so the `states` association of the countries will be correctly loaded.
