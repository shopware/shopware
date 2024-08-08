---
title: Set correct asset path for bundle assets
issue: NEXT-37175
---
# Core
* Changed `\Shopware\Core\Framework\Adapter\Asset\AssetPackageService::create` method to also strip the `bundle` suffix when generating the path, to be in line with `\Shopware\Core\Framework\Plugin\Util\AssetService::getTargetDirectory`, thus fixing usages of `@MyBundle` asset usages.
