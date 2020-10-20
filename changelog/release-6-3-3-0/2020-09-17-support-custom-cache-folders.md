---
title: Support custom cache folders
issue: NEXT-10854
author: Yann Karl
author_email: y.karl@webweit.de 
author_github: @yannick80
---
# Core
*  Changed property name `cacheDir` to `projectDir`, updated write path for `dump` in `src/Core/Framework/Plugin/BundleConfigDumper.php`
*  Changed parameter from `%kernel.cache_dir%` to `%kernel.project_dir%` in service description for `BundleConfigDumper` in `src/Core/Framework/DependencyInjection/plugin.xml`
___
# Storefront
*  Changed property name `cacheDir` to `projectDir`, updated write path for `execute`  in `src/Storefront/Theme/Command/ThemeDumpCommand.php`
*  Changed parameter `%kernel.cache_dir%` to `%kernel.project_dir%` in service description for `ThemeDumpCommand` in `src/Storefront/DependencyInjection/theme.xml`
*  Changed parameter `kernel.cache_dir` to `kernel.project_dir` for `testExecuteShouldResolveThemeInheritanceChainAndConsiderThemeIdArgument` in `src/Storefront/Test/Theme/Command/ThemeDumpCommandTest.php`
