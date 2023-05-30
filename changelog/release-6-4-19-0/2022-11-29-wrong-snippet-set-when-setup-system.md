---
title: Wrong snippet set when setup system
issue: NEXT-22362
---
# Core
* Changed the `execute` function in `src/Core/Maintenance/System/Command/SystemInstallCommand.php`.
___
# Storefront
* Changed the `configure` function in `src/Storefront/Framework/Command/SalesChannelCreateStorefrontCommand.php` to add command option iso code.
* Changed the `getSalesChannelConfiguration` function in `src/Storefront/Framework/Command/SalesChannelCreateStorefrontCommand.php` to get sales channel config.
* Changed the `getSnippetSetId` function in `src/Storefront/Framework/Command/SalesChannelCreateStorefrontCommand.php` to the get snippet set id correct.
