[titleEn]: <>(Modifying the blacklist from Dynamic product groups)
[metaDescriptionEn]: <>(This HowTo will show you how to modify the property blacklist of the "Dynamic product groups" module.)
[hash]: <>(article:modify_blacklist_dynamic_product_groups)

## Overview

The module "Dynamic product groups" includes a condition builder to properly configure your dynamic product groups.
You might have noticed though, that this condition builder does not show all available properties,
since some of them are blacklisted in the code, such as e.g. `createdAt`.

In this HowTo you'll get two quick examples on how to either add new properties to this blacklist or even remove
properties from the blacklist, so they're actually shown in the administration and thus can be used.

## Prerequisites

This HowTo **will not** explain in detail how to override an existing component.
For this HowTo you'll have to extend the component [sw-product-stream-field-select](https://github.com/shopware/platform/blob/master/src/Administration/Resources/app/administration/src/module/sw-product-stream/component/sw-product-stream-field-select/index.js)
though, since it's the one [actually checking for the properties in the computed property options](https://github.com/shopware/platform/blob/master/src/Administration/Resources/app/administration/src/module/sw-product-stream/component/sw-product-stream-field-select/index.js#L41).

An example on how to override a component can be found [here](./190-add-admin-new-column.md).

## Adding properties to blacklist

As already mentioned in the prerequisites, the check for properties in the blacklist is done in the computed property `options`.
Therefore you'll have to make sure your modifications are done **before** the check happens.

```js
const { Component } = Shopware;

Component.override('sw-product-stream-field-select', {
    computed: {
        options() {
            this.conditionDataProviderService.addToGeneralBlacklist(['deliveryTimeId']);
            return this.$super('options');
        }
    }
});
```

This example will simply add the property `deliveryTimeId` to the blacklist, so it's not configurable using the Administration anymore.
There's also nested properties, so called 'entity properties', which are selectable once you've chosen a property such as `Categories`.
Those entity properties can also be added to the blacklist by using the method `addToEntityBlacklist` instead:

```js
const { Component } = Shopware;

Component.override('sw-product-stream-field-select', {
    computed: {
        options() {
            this.conditionDataProviderService.addToEntityBlacklist('category', ['breadcrumb']);
            return this.$super('options');
        }
    }
});
```

This example would forbid the usage of `breadcrumb` from the `category` entity.

## Removing properties from the blacklist

Most likely you'd want to do the opposite and enable properties by removing entries from the blacklist.
This can be done exactly like adding properties to the blacklist.
One method to remove a property from the "general blacklist", which is the first dropdown, or from the "entity blacklist",
which contains the properties of the previously selected entity.

```js
const { Component } = Shopware;

Component.override('sw-product-stream-field-select', {
    computed: {
        options() {
            this.conditionDataProviderService.removeFromGeneralBlacklist(['createdAt']);
            this.conditionDataProviderService.removeFromEntityBlacklist('category', ['path']);
            return this.$super('options');
        }
    }
});
```

This example enables both the general `createdAt` property, as well as the category property `path`.
And that's it already!
