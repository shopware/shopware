---
title: Fix HTML locale attribute
issue: -
author: Silvio Kennecke
author_email: development@silvio-kennecke.de
author_github: @silviokennecke
---
# Storefront
* Changed `HeaderPageletLoader::getLanguages` to load associated locale
* Changed `base.html.twig` to use `activeLanguage.locale` instead of `activeLanguage.translationCode`
