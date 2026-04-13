---
title: Remove DeleteThemeFilesMessage usage in ThemeCompiler
issue: #7925
author: Michael Telgmann
author_github: @mitelg
---
# Storefront
* Added `\Shopware\Storefront\Theme\ScheduledTask\DeleteThemeFilesTask` and `\Shopware\Storefront\Theme\ScheduledTask\DeleteThemeFilesTaskHandler` to handle the deletion of unused theme files via a scheduled task.
* Changed `\Shopware\Storefront\Theme\ThemeCompiler::compileTheme` method, so it is no longer dispatching the `\Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage` message.
* Deprecated `\Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage` and `\Shopware\Storefront\Theme\Message\DeleteThemeFilesHandler` as they are no longer needed.

___
# Upgrade Information

## Deprecation of DeleteThemeFilesMessage and its handler
The `\Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage` and its handler `\Shopware\Storefront\Theme\Message\DeleteThemeFilesHandler` are deprecated.
They are no longer used by the core and will be removed in the next major version.
Unused theme files are now deleted by using the `\Shopware\Storefront\Theme\ScheduledTask\DeleteThemeFilesTask` scheduled task.
