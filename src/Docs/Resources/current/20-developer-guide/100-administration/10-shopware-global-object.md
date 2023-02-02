[titleEn]: <>(Shopware global object)
[hash]: <>(article:developer_administration_shopware_global_object)

`Shopware` is a global object and it was created for third party developers. It is mainly the bridge between the Shopware Administration and your plugin.  

You can inspect the global `Shopware` object in your browser with the developer tools, because it is bound to the `window` object.
Open the `Administration` in your browser an inspect the page with die dev-tools.
In the console you can run:
 
```js
// run this command in the dev-tools of your browser
console.log(Shopware);
```

There are lots of things bound to this object. Here is short overview of the most commonly used parts. 

## Component

The `Component` property of the global `Shopware` contains the component registry and is responsible for handling the VueJS components.
If you want to write your own components you have to register them with the `Component.register()` method.
Components are small reusable building blocks which you can use to implement your features. These components are VueJS components.

## Module

The `Module` property of the global `Shopware` contains the module registry.
A `Module` is a encapsulated unit which implement a whole feature. For example there are modules for customers, orders, settings, etc.
Use modules to implement complex features you want to implement.

## Overview

Here is a short overview of the commonly uses properties of the global `Shopware` object.

| Property   | Description                                                                                  |
|------------|----------------------------------------------------------------------------------------------|
| ApiService | Registry which holds services to fetch data from the api                                     |
| Component  | A registry for VueJS `components`                                                            |
| Context    | A set of contexts for the `app` and `api`                                                    |
| Defaults   | A collection of default values                                                               |
| Directive  | A registry for VueJS `directives`                                                            |
| Filter     | A registry for template `filters`                                                            |
| Helper     | A collection of helpers, e.g. the `DeviceHelper` where you can listen on the `resize` event. |
| Locale     | A registry for `locales`                                                                     |
| Mixin      | A registry for `mixins`                                                                      |
| Module     | A registry for `modules`                                                                     |
| Plugin     | An interface to add `promise` hooks when the administration launches                         |
| Service    | A helper to get quick access to service, e.g. `Shopware.Service('snippetService')`           |
| Shortcut   | A registry for keyboard shortcuts                                                            |
| State      | A wrapper for the VueX store to manage state                                                 |
| Utils      | A collection of utility methods like `createId`                                              |

