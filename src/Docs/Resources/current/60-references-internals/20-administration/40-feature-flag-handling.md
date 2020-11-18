[titleEn]: <>(Feature flag handling)
[hash]: <>(article:admin_feature_flag_handling)

This short guide will introduce you to the best practices when you need to make use of currently not released feature gated by a feature flag.
For a complete overview about feature flags take a look [here](./../../60-references-internals/10-core/20-feature-flag-handling.md)

## Caution!
**Everything gated by a feature flag is declared as Work in Progress**, do not rely on code gated by a feature flag and do not release plugins that make use of feature flags.
Do not implement own feature flags, they will be broken!

This example is for your PHP code. 
* For examples for the storefront look [here](./../../60-references-internals/30-storefront/50-feature-flag-handling.md)
* For examples for php look [here](./../../60-references-internals/40-plugins/90-feature-flag-handling.md)

For better understanding we are using an example. Let's imagine you want to implement a change in your plugin for a feature we haven't currently deployed, but is gated by a feature flag in the core code.


First we will assume the feature flag is called "FEATURE_NEXT_123" and you want to register a component only if this feature is active then you can just add the _flag_ property with the flag name and your component will only be registered if the feature flag is active.

#### Your custom feature flag dependable component
```js
Module.register('my-own-component-for-feature', {
    flag: 'FEATURE_NEXT_123',
    ...
});
```

If you only want to change specific parts in your component or your template you can inject the _feature_ service and use it as follows:

In js:
```js
inject: ['feature'],
...
doSomething(param) {
            if(this.feature.isActive('FEATURE_NEXT_123')) {
                return myNewFunction(param);            
            } else {
                return myOldFunction(param);
            }   
        },
```

In templates: (you have to inject the service in your component as above)
```vue
<sw-field type="text" v-if="feature.isActive('FEATURE_NEXT_1128')"></sw-field>
```
