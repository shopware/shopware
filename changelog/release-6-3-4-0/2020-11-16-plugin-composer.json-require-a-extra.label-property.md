---
title: Plugin composer.json require a extra.label property
issue: NEXT-8248
---
# Core
*  Added a conditional check in `\Shopware\Core\Framework\Plugin\Util\PluginFinder::isPluginComposerValid` that requires a plugin's composer.json file to define a extra.label property.
