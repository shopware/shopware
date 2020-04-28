[titleEn]: <>(Add new column to table in administration)
[metaDescriptionEn]: <>(This article will give a brief explanation on how to add a new column to an existing table in the administration. In this example, the product list table will be extended.)
[hash]: <>(article:how_to_new_admin_column)

## Overview

This article will give a brief explanation on how to add a new column to an existing table in the administration.
In this example, the product list table will be extended.

## Setup

This HowTo **does not** explain how to create a new plugin for Shopware 6.
Head over to our [developer guide](./../20-developer-guide/10-plugin-base.md) to
learn creating a plugin at first.

## Injecting into the administration

The main entry point to customize the administration via plugin is the `main.js` file.
It has to be placed into a `<plugin root>/src/Resources/app/administration/src` directory in order to be found by Shopware 6.

Your `main.js` file then needs to override the [Vue component](https://vuejs.org/v2/guide/components.html) using the
`override` method of our `ComponentFactory`.

The first parameter matches the component to override, the second parameter is an object containing
the actually overridden properties, including a new template for the component if necessary.
Since the columns of a table are defined in javascript, you don't have to extend the twig template here.

```js
Shopware.Component.override('sw-product-list', {
    computed: {
        productColumns() {
            let columns = this.getProductColumns();

            columns.push({
                property: 'manufacturer.id',
                dataIndex: 'manufacturer.id',
                label: 'Manufacturer ID',
                inlineEdit: 'string',
                allowResize: true,
                align: 'left'
            });

            return columns;
        },
    },
});
```

In this case, the `sw-product-list` component is overridden, which reflects the product list table.
As mentioned above, the columns are defined in javascript. This is done via the computed property `productColumns`,
which is the method that has to be overridden in order to add a new column.

You might have noticed, that the manufacturer's ID is to be displayed in the new column.

## Loading the JS files

As mentioned above, Shopware 6 is looking for a `main.js` file in your plugin.
Its contents get minified into a new file named after your plugin and will be moved to the `public` directory
of the Shopware 6 root directory.
Given this plugin would be named "AdministrationNewColumn", the minified javascript code for this example would be
located under `<plugin root>/src/Resources/public/administration/js/administration-new-column.js`, once you run the command `./psh.phar administration:build` in your shopware root directory.
*Note: Your plugin has to be activated for this to work.*
Make sure to also include that file when publishing your plugin!
A copy of this file will then be put into the directory `<shopware root>/public/bundles/administrationnewcolumn/administration/js/administration-new-column.js`.

The latter javascript file has to be injected into the template by your plugin as well for production environments.
In order to do this, create a new file called `index.html.twig` here: `<plugin root>/src/Resources/views/administration/`

```twig
{% sw_extends 'administration/index.html.twig' %}

{% block administration_scripts %}
    {{ parent() }}
    <script type="text/javascript" src="{{ asset('bundles/administrationnewcolumn/administration/js/administration-new-column.js') }}"></script>
{% endblock %}
```

Your minified javascript file will now be loaded in production environments.

In the next step, you might want to have a look at our explanation on [how to extend a module to show a new field](./200-add-admin-new-field.md), e.g. on the product's
detail page.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-administration-new-column).
