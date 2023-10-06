---
title: Fixed exception on missing theme field value
issue: NEXT-20091
author: Stefan Sluiter
author_email: s.sluiter@shopware.com
---
# Storefront
* Changed `Shopware\Storefront\Theme\ThemeService::getThemeConfiguration` to not throw an excpetion if no initial value is given for a theme config field
