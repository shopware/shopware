---
title: Changed theme refresh command that no media will be deleted from a dublicated theme
issue: NEXT-17465
author: Marcel Hakvoort
author_email: m.hakvoort@shopware.com
author_github: @celha
---
# Storefront
* Changed method `refreshTheme()` in `src/Storefront/Theme/ThemeLifecycleService.php` that no media from a dublicated theme will be removed after using `bin/console theme:refresh` command
