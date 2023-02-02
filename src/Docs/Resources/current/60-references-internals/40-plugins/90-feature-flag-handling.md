[titleEn]: <>(Feature flag handling)
[hash]: <>(article:plugin_feature_flag_handling)
This short guide will introduce you to the best practices when you need to make use of currently not released feature gated by a feature flag.
For a complete overview about feature flags take a look [here](./../../60-references-internals/10-core/20-feature-flag-handling.md)

## Caution!
**Everything gated by a feature flag is declared as Work in Progress**, do not rely on code gated by a feature flag and do not release plugins that make use of feature flags.
Do not implement own feature flags, they will be broken!

This example is for your PHP code. 
* For examples for the administration look [here](./../../60-references-internals/20-administration/40-feature-flag-handling.md)
* For examples for the storefront look [here](./../../60-references-internals/30-storefront/50-feature-flag-handling.md)

For better understanding we are using an example. Let's imagine you want to implement a change in your plugin php code for a feature we haven't currently deployed, but is gated by feature flag in the core code.


First we will assume the feature flag is called "FEATURE_NEXT_123" and you want to change a method with feature dependent data in your class only if this feature is active, just do as follows:

#### Your custom PHP class
```php

use Shopware\Core\Framework\Feature;

class MyPluginClass
{
  public function doSomething($param): int
  {
     if (Feature::isActive('FEATURE_NEXT_123')) {
        return $this->myNewFunction($param);
     } else {
        return $this->myOldFunction($param);
     }   
 
     // test code
  }
}
```


