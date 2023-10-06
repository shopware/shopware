---
title: Added option to skip assets compilation to system:update:finish command.
issue: NEXT-22008
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed `Shopware\Storefront\Theme\Subscriber\UpdateSubscriber` to check if the state `Shopware\Core\Framework\Plugin\PluginLifecycleService::STATE_SKIP_ASSET_BUILDING` exists.
* Changed `Shopware\Core\Maintenance\System\Command\SystemUpdateFinishCommand` to add a new command option `--skip-asset-build`.
* Changed `Shopware\Core\Maintenance\System\Command\SystemUpdateFinishCommand` to add the state `Shopware\Core\Framework\Plugin\PluginLifecycleService::STATE_SKIP_ASSET_BUILDING` if the `--skip-asset-build` option has been provided.
