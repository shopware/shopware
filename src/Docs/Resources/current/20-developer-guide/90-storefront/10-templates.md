[titleEn]: <>(Templates)
[metaDescriptionEn]: <>(Twig templates in the Shopware 6 storefront)
[hash]: <>(article:developer_storefront_templates)

The storefront theme is using [Boostrap](https://getbootstrap.com/). 
Therefore the template structure is a derivative of the 
[bootstrap starter template](https://getbootstrap.com/docs/4.3/getting-started/introduction/#starter-template). 
The templating engine used is [Twig](https://twig.symfony.com/).
For styling [SASS](https://sass-lang.com/) is used as CSS preprocessor.
The bundling and transpiling of the javascript is done with [Webpack](https://webpack.js.org/).

The templates can be found in 
[`/src/Storefront/Resources/views/storefront/`](https://github.com/shopware/platform/tree/master/src/Storefront/Resources/views) 

## Template Top Level 

```
<platform/src/Storefront/Resources/views/storefront/>
└── block
└── component
└── element
└── layout
└── page
└── section
└── utilities
└── base.html.twig
```

`block`, `element`, `section` 
  : Parts of the experience worlds
  
`component`
  : Shared content templates form the basis of the pages.

`layout`  
  : Layout templates. Navigation, header and footer content templates are located here.  

`page`  
  : The concrete templates rendered by the page controllers. This directory contains full page templates 
  as well as private local includes and the pagelet ajax response templates if necessary.  

`utilities`  
  : Technical necessities used across the content and across all domain concepts.   

`base.html.twig`  
  : Base page layout of the storefront. This file mainly includes header and footer templates from `/layout` 
  and provides blocks for the `/page` templates to  overwrite.

### Page templates

The page directory contains the entry points which are referenced by page controllers 
and rendered through the Twig engine. The structure is derived from the 
[page controller](https://github.com/shopware/platform/tree/master/src/Storefront/PageController) naming.

```
<platform/src/Storefront/Resources/views/storefront/page>
└── account
└── checkout
└── content
└── error
└── newsletter
└── product-detail
└── search
```

Inside the directories are the actual templates rendered by the storefront. The inner structure depends on 
the domain context.

### Override and extend templates

Due to the plugin and theme system in shopware it is possible that one storefront template gets extended by 
multiple plugins or themes, but [Twig](https://twig.symfony.com/) does not allow multi inheritance out of the box. 
Therefore we created custom twig functions `sw_extends` and `sw_include`, that work like twigs 
native [`extends`](https://twig.symfony.com/doc/2.x/tags/extends.html) or 
[`include`](https://twig.symfony.com/doc/2.x/tags/include.html), except that they allow for multi inheritance. 
So it is really important to use the `sw_extends` and `sw_include`, instead of the native `extends` and `include`. 

#### sw_extends

To inherit a template file, you need to use `{% sw_extends %}`.

Example:
```twig
{# YourPlugin/Resources/views/storefront/layout/header/logo.html.twig #}

{% sw_extends '@Storefront/storefront/layout/header/logo.html.twig' %}

{# Add an <h2> with underneath the logo image block #}
{% block layout_header_logo_image %}
    {{ parent() }}
    <h2>Additional headline</h2>
{% endblock %}

```

#### sw_include

If you build your own feature and you need e.g. an element to display the price of the current product you can
include existing partials with `sw_include` like this.

Example:
```twig
{# MyPlugin/Resources/views/storefront/page/product-detail/index.html.twig #}

<div class="my-theme an-alternative-product-view">
    ...

    {% block component_product_box_price %}
        {# use sw_include to include template partials #}
        {% sw_include '@Storefront/storefront/component/product/card/price-unit.html.twig' %}
    {% endblock %}

    ...
</div>
```

#### Inheritance order

The order of the inheritance is determined by the order you set in the `theme.json` of your active theme. 

## Styles Top Level

The stylesheets are written in SASS. The organization is inspired by the 
[7-1 pattern](https://sass-guidelin.es/#architecture) structure. 

```
<platform/src/Storefront/Resources/app/storefront/src/scss>
└── abstract
└── base
└── component
└── layout
└── page
└── skin
└── vendor
└── base.scss
```

The `base.scss` is the global include file which references styles that are written as an extension of the bootstrap 
base. For further information just take a look at the excellent description at 
[sass-guidelines.es](https://sass-guidelin.es/#architecture).


## Scripts Top Level

The storefront includes a set of JavaScript plugins providing different functionalities to the storefronts templates 
on the client side.
The plugins are written as **ES6 classes** in **vanilla JavaScript**. Additionally since bootstrap is distributed 
with the [jQuery library](https://jquery.com/) the storefront also contains this library.

The `script` root looks like this: 

```
<platform/src/Storefront/Resources/app/storefront/src/script>
└── config
└── helper
└── plugin
└── service
└── utility
└── vendor
└── base.js
```

## Sanitize content

Filters tags and attributes from a given string.

The filter can be found in 
[`/src/Storefront/Framework/Twig/Extension/SwSanitizeTwigFilter.php`](https://github.com/shopware/platform/blob/master/src/Storefront/Framework/Twig/Extension/SwSanitizeTwigFilter.php)

Examples:
`{{ unfilteredHTML|sw_sanitize }}` 
  : Uses the default config
  
`{{ unfilteredHTML|sw_sanitize( {'div': ['style', ...]}, true ) }}`
  : **allow only** div tags + style attribute for div
