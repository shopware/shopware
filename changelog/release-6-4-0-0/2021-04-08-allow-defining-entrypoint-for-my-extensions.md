---
title: Allow defining entrypoint for my extensions
issue: NEXT-14524
---
# Administration
* Changed module registration to allow specifying an entrypoint for my extensions

Example configurations:

```js
Module.register('my-module', {
    ...

    extensionEntryRoute: {
        extensionName: 'MyPlugin', // My extension name
        route: 'the.route.entrypoint' // The route to redirect on click on the Open app link
    }
});
```


```js
Module.register('my-module', {
    ...

    extensionEntryRoute: {
        extensionName: 'MyPlugin', // My extension name
        route: 'the.route.entrypoint', // The route to redirect on click on the Open app link
        label: 'my-plugin.some.label' // Translation snippet to override the label of the link
    }
});
```


