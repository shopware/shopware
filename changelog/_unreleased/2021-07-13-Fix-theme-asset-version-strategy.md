---
title: Fix VersionStrategy application of ThemeAssetPackage 
issue: NEXT-5646
---
# Core
* Changed `\Shopware\Core\Framework\Adapter\Asset\ThemeAssetPackage::getUrl` to pass correct relative file path to version strategy, thus fixing cache busting for theme asset files.
