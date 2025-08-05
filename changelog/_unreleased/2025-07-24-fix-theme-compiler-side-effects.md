---
title: Fix ThemeCompiler side effects
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Storefront
* Changed `Shopware\Storefront\Theme\ThemeCompiler` to use `ThemeFileResolver->resolveStyleFiles` to only calculate required files
* Changed `Shopware\Storefront\Theme\ThemeRuntimeConfigService` to use `ThemeFileResolver->resolveScriptFiles` to only calculate required files
* Changed `Shopware\Storefront\Theme\ThemeFileResolver` to public provide `resolveScriptFiles` & `resolveStyleFiles` functions
