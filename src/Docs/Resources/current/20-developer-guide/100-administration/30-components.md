[titleEn]: <>(Components)
[hash]: <>(article:developer_administration_components)

Component are the building blocks which you can use to implement your plugin features.
You can find various components which comes with Shopware 6 in the [component library](https://component-library.shopware.com/).

To build you own component you have to register them in the administration to the component like this:

```js
import template from './my_custom_component.html.twig';
import './my_custom_component.scss';

Component.register('my-custom-component', {
    template
});
```

Now you component is registered to the component factory.
you can make use of it like a normal VueJS component. The component factory build generate a VueJS component for every registered component.

To share state across different components you can use `Shopware.State` to subscribe to stores or create new ones.
`Shopware.State` is a wrapper for the [VueX](https://vuex.vuejs.org) plugin which Shopware 6 uses behind the scenes.
