---
title: Install Shopware success with another locale
issue: NEXT-25623
---
# Storefront
* Changed the `getSalesChannelConfiguration` function in `src/Storefront/Framework/Command/SalesChannelCreateStorefrontCommand.php` to get default snippet set.
* Added the `guessSnippetSetId` function in `src/Storefront/Framework/Command/SalesChannelCreateStorefrontCommand.php` to get default snippet set if snippet set not exists.
* Changed the `getSnippetSetId` function in `src/Storefront/Framework/Command/SalesChannelCreateStorefrontCommand.php` to remove throw exception.
