---
title: Improved error handling for app lifecycle commands
issue: NEXT-12229
---
# Core
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle::install()`-method to throw `\Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException` if the app is already installed.
* Changed `\Shopware\Core\Framework\App\Command\InstallAppCommand` to catch AppAlreadyInstalledException and report that to the user.
* Changed `\Shopware\Core\Framework\App\Command\RefreshAppCommand` to print the reason for install or update failures.
* Deprecated `\Shopware\Core\Framework\App\Lifecycle\AppLifecycleIterator::iterate()`-method, use `\Shopware\Core\Framework\App\Lifecycle\AppLifecycleIterator::iterateOverApps()` instead. 
* Deprecated `\Shopware\Core\Framework\App\AppService::refreshApps()`-method, use `\Shopware\Core\Framework\App\AppService::doRefreshApps()` instead. 
