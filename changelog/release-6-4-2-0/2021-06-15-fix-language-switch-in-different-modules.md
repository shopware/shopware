---
title: Fix language switch in different modules
issue: NEXT-10476
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: jleifeld
---
# Administration
* Added parameter languageId to method `onChangeLanguage` in `sw-mail-template-detail`
* Added return value to method `onSave` in `sw-mail-template-detail`
* Changed hasChanges check in method `abortOnLanguageChange` in `sw-product-detail`
