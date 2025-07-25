---
title: Fix ThemeLifecycleService refreshThemes being executed without plugin configurations
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Storefront
* Changed `Shopware\Storefront\Theme\ThemeLifecycleService` to correctly use the complete plugin configuration for the configurationCollection of `refreshTheme`
