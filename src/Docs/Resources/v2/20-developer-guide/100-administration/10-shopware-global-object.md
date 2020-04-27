[titleEn]: <>(Shopware global object)
[hash]: <>(article:developer_administration_shopware_global_object)

`Shopware` is a global object and it was created for third party developers. It is mainly the bridge between the Shopware Administration and your plugin.  

You can inspect the global `Shopware` object in your browser with the developer tools, because it is bound to the `window` object.
Open the `Administration` in your browser an inspect the page with die dev-tools.
In the console you can run:
 
```js
// run this command in the dev-tools of your browser
console.log(window.Shopware);
```

There are lots of things bound to this object. Here is short overview of the most commonly used parts. 

## Component

The `Component` property of the global `Shopware` contains the component registry and is responsible for handling the Vue Js components.
If you want to write your own components you have to register them with the `Component.register()` method.
Components are small reusable building blocks which you can use to implement your features.

## Module

The `Module` property of the global `Shopware` contains the module registry is responsible for registering the Vue Js modules.
A `Module` is a encapsulated unit which implement a whole feature. For example there are modules for customers, orders, settings, etc.
Use modules to implement complex features you want to implement.
