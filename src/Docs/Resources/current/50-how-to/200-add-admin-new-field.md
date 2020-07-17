[titleEn]: <>(Add field to module in administration)
[metaDescriptionEn]: <>(If you were wondering, how you can add a new field to an existing module in the Administration via plugin, then you've found the right HowTo to cover that subject.)
[hash]: <>(article:how_to_new_admin_field)

## Overview

If you were wondering, how you can add a new field to an existing module in the Administration via plugin, then you've
found the right HowTo to cover that subject.
In the following examples, you'll add a new field to the product's detail page, to display and configure
some other product data not being shown by default.

## Setup

This HowTo **does not** explain how you can create a new plugin for Shopware 6.
Head over to our [developer guide](./../20-developer-guide/10-plugin-base.md) to
learn creating a plugin at first.

## Injecting into the administration

The main entry point to customize the administration via plugin is the `main.js` file.
It has to be placed into a `<plugin root>/src/Resources/app/administration/src` directory in order to be automatically found by Shopware 6.

Your `main.js` file then needs to override the [Vue component](https://vuejs.org/v2/guide/components.html) using the
`override` method of our `ComponentFactory`.

The first parameter matches the component to override, the second parameter has to be an object containing
the actually overridden properties , e.g. the new twig template extension for this component.

```js
import template from './src/extension/sw-product-settings-form/sw-product-settings-form.html.twig';

Shopware.Component.override('sw-product-settings-form', {
    template
});
```

In this case, the `sw-product-settings-form` component is overridden, which reflects the settings form on the product detail page.
As mentioned above, the second parameter has to be an object, which includes the actual template extension.

## Adding the custom template

Time to create the referenced twig template for your plugin now.
*Note: We're dealing with a [TwigJS](https://github.com/twigjs/twig.js/wiki) template here.* 

Create a file called `sw-product-settings-form.html.twig` in the following directory:
`<plugin root>/src/Resources/app/administration/src/extension/sw-product-settings-form`

*Note: The path starting from 'src' is fully customizable, yet we recommend choosing a pattern like this one.*

```twig
{% block sw_product_settings_form_content %}
    {% parent %}

    <sw-container columns="repeat(auto-fit, minmax(250px, 1fr)" gap="0px 30px">
        <sw-text-field label="Manufacturer ID" v-model="product.manufacturerId" disabled></sw-text-field>
    </sw-container>
{% endblock %}
```

Basically the twig block `sw_product_settings_form_content` is overridden here.
Make sure to have a look at the [Twig documentation about the template inheritance](https://twig.symfony.com/doc/2.x/templates.html#template-inheritance), to understand how blocks in Twig work.

This block contains the whole settings form of the product detail page.
In order to add a new field to it, you need ot override the block, call the block's original content (otherwise we'd replace the whole form), and then
add your custom field to it - that's what is done in there.
Also, the field is "disabled", since it should be readable only.
This should result in a new field with the label 'Manufacturer ID', which then contains the ID of the actually chosen manufacturer.

## Loading the JS files

As mentioned above, Shopware 6 is looking for a `main.js` file in your plugin.
Its contents get minified into a new file named after your plugin and will be moved to the `public` directory
of Shopware 6 root directory.
Given this plugin would be named "AdministrationNewField", the minified javascript code for this example would be
located under `<plugin root>/src/Resources/public/administration/js/administration-new-field.js`, once you run the command `./psh.phar administration:build` in your shopware root directory.
*Note: Your plugin has to be activated for this to work.*
Make sure to also include that file when publishing your plugin!
A copy of this file will then be put into the directory `<shopware root>/public/bundles/administrationnewfield/administration/js/administration-new-field.js`.

The latter javascript file has to be injected into the template by your plugin as well for production environments.
In order to do this, create a new file called `index.html.twig` here: `<plugin root>/src/Resources/views/administration/`

```twig
{% sw_extends 'administration/index.html.twig' %}

{% block administration_scripts %}
    {{ parent() }}
    <script type="text/javascript" src="{{ asset('bundles/administrationnewfield/administration/js/administration-new-field.js') }}"></script>
{% endblock %}
```

Your minified javascript file will now be loaded in production environments.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-administration-new-field).
