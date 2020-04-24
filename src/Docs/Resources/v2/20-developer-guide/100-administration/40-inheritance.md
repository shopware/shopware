[titleEn]: <>(Inheritance)
[hash]: <>(article:developer_administration_inheritance)

To add new functionality or change the behavior of a existing component, you can either override or extend the component.

The difference between the two methods is that with `Component.extend()` a new component is created. With `Component.override()`, on the other hand, the previous behavior of the component is simply overwritten.

## Override a component

The following example shows how you can override the template of the `sw-text-field` component.

```js
// import the new twig-template file
import template from './sw-text-field-new.html.twig';

const { Component } = Shopware;

// override the existing component `sw-text-field` by passing the new configuration
Component.override('sw-text-field', {
    template
});
```

## Extending a component

To create your custom text-field `sw-custom-field` based on the existing `sw-text-field` you can implement it like following.

```js
// import the custom twig-template file
import template from './sw-custom-field.html.twig';

const { Component } = Shopware;

// extend the existing component `sw-text-field` by passing
// a new component name and the new configuration
Component.extend('sw-custom-field', 'sw-text-field', {
    template
});
```

Now you can render your new component `sw-custom-field` in any template like this.

```twig
    <sw-custom-field></sw-custom-field>
```

## Customize a component template

To extend a given template you can use the Twig `block` feature.

Imagine you component you want to extend/override has the following template.

```twig
{% block card %}
    <div class="sw-card">
        {% block card_header %}
            <div class="sw-card--header">
                {{ header }}
            </div>
        {% endblock %}

        {% block card_content %}
            <div class="sw-card--content">
                {{ content }}
            </div>
        {% endblock %}
    </div>
{% endblock %}
```

Maybe you want to replace the markup of the header section an add an extra block to the content.
With the Twig `block` feature you can implement a solution like this.

```twig
{# override/replace an existing block #}
{% block card_header %}
    <h1 class="custom-header">
        {{ header }}
    </h1>
{% endblock %}

{% block card_content %}

    {# render the original block #}
    {% parent %}

    <div class="card-custom-content">
        ...
    </div>
{% endblock %}
```

Summarized with the `block` feature you will be able to replace blocks inside a template.
Additionally you can render the original markup of a block by using `{% parent %}`

## Extending methods and computed properties

Sometimes you need to change the logic of a method or a computed property while you are extending/overriding a component.
In the following example we extend the `sw-text-field` component and change the `onInput()` method, which gets called after the value of the input field changes.

```js
const { Component } = Shopware;

// extend the existing component `sw-text-field` by passing
// a new component name and the new configuration
Component.extend('sw-custom-field', 'sw-text-field', {

    // override the logic of the onInput() method
    methods: {
        onInput() {
            // add your custom logic in here
            // ...
        }
    }
});
```

In the previous example the inherited logic of `onInput()` will be replaced completely.
But sometimes you only be able to add additional logic to the method. You can achieve this by using `this.$super()` call.

```js
const { Component } = Shopware;

// extend the existing component `sw-text-field` by passing
// a new component name and the new configuration
Component.extend('sw-custom-field', 'sw-text-field', {

    // extend the logic of the onInput() method
    methods: {
        onInput() {
            // call the original implementation of `onInput()`
            const superCallResult = this.$super('onInput');

            // add your custom logic in here
            // ...
        }
    }
});
```

This technique also works for `computed` properties, e.g.

```js
const { Component } = Shopware;

// extend the existing component `sw-text-field` by passing
// a new component name and the new configuration
Component.extend('sw-custom-field', 'sw-text-field', {

    // extend the logic of the computed property `stringRepresentation`
    computed: {
        stringRepresentation() {
            // call the original implementation of `onInput()`
            const superCallResult = this.$super('stringRepresentation');

            // add your custom logic in here
            // ...
        }
    }
});
