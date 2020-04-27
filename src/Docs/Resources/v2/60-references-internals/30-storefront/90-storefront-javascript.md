[titleEn]: <>(Storefront JavaScript)
[hash]: <>(article:references_storefront_javascript)

## Configuring JavaScript plugins

You can configure your plugins from inside the templates via data-options.
Define a static `options` object inside your plugin and assign your options with default values to it.
In your case define a text option and as a default value use the text you previously directly prompted to the user.
And instead of the hard coded string inside the `alert()` use your new option value.

```js
// src/Resources/app/storefront/src/main.js

import Plugin from 'src/plugin-system/plugin.class';

export default class ExamplePlugin extends Plugin {
    static options = {
        /**
         * Specifies the text that is prompted to the user
         * @type string
         */
        text: 'seems like there\'s nothing more to see here.',
    };

    init() {
        const that = this;
        window.onscroll = function() {
            if ((window.innerHeight + window.pageYOffset) >= document.body.offsetHeight) {
                alert(that.options.text);
            }
        };
    }
}
```

Now you are able to override the text that is prompted to the user from inside your templates.
Therefore create an `product-detail` folder inside your `Resources/views/storefront/page` folder and add an `index.html.twig` file inside that folder.
In your template extend from `@Storefront/storefront/page/product-detail/index.html.twig` and override the `page_product_detail_content`.
After the parent content add an template tag with the `data-example-plugin` tag also to activate your plugin on product detail pages.
Next add an `data-{your-plugin-name-in-kebab-case}-options` (`data-example-plugin-options`) attribute to the DOM element you registered your plugin on (the template tag).
As the value of this attribute use the options you want to override as a json object.

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}

{% set examplePluginOptions = {
    text: "Are you not interested in this product?"
} %}

{% block page_product_detail_content %}
    {{ parent() }}

    <template data-example-plugin data-example-plugin-options='{{ examplePluginOptions|json_encode }}'></template>
{% endblock %}
```

It is best practice to use a variable for the options because this is extendable from plugins.
