---
title: Added async theme compilation configuration
issue: NEXT-29828
author: Stefan Sluiter
author_email: s.sluiter@shopware.com
---
# Storefront
* Changed `sw-settings-storefront-configuration.html.twig` and `modules/sw-settings-storefront/page/sw-settings-storefront-index/index.js` to add async compilatin setting.
* Added `Shopware\Storefront\Theme\Message\CompileThemeMessage` for theme compiling messages.
* Added `Shopware\Storefront\Theme\Message\CompileThemeHandler` as a handler for `Shopware\Storefront\Theme\Message\CompileThemeMessage` messages.
* Changed `Shopware\Storefront\Theme\ThemeService::compileTheme` and `Shopware\Storefront\Theme\ThemeService::compileThemeById` to check whether the compiling should be done asynchronously.
* Changed `Shopware\Storefront\Theme\ThemeService` by adding `reset` method.
* Changed `Shopware\Storefront\Theme\ThemeService` to implement the ResetInterface
___
# Upgrade Information
## Async theme compilation (@experimental)

It is now possible to trigger the compilation of the storefront css and js via the message queue instead of directly 
inside the call that changes the theme or activates/deactivates an extension.

You can change the compilation type with the system_config key `core.storefrontSettings.asyncThemeCompilation` in the 
administration (`settings -> system -> storefront`)
