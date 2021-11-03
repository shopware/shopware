---
title: Load additional bundles in order
issue: NEXT-17948
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed bundle loading order by the keys from `\Shopware\Core\Framework\Plugin::getAdditionalBundles` in `\Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader::getBundles` to allow bundle loading prior the plugin itself
___
# Upgrade Information
When you depend on a self-shipped bundle to already been loaded before your plugin, you can now use negative keys in `getAdditionalBundles` to express a different order. Use negative keys to load them before your plugin instance:

```
class AcmePlugin extends Plugin
{
    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        return [
            -10 => new DependencyBundle(),
        ];
    }
}
```
