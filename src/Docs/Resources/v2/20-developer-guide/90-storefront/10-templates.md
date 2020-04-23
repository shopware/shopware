[titleEn]: <>(Templates)
[metaDescriptionEn]: <>(Twig templates in the Shopware 6 storefront)
[hash]: <>(article:developer_storefront_templates)

The storefront theme is implemented as a skin on top of the [Boostrap toolkit](https://getbootstrap.com/). 
Therefore the template structure is a derivate of the 
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

`block`, `element`
  : Part of the content management system
  
`component`
  : Shared content templates form the basis of the pages.

`layout`  
  : Layout templates. Navigation, header and footer content templates are located here.  

`page`  
  : The concrete templates rendered by the page controllers. This directory contains full page templates 
  as well as private local includes and the pagelet ajax response templates if necessary.  

`section`  
  : Storefront templates of the sections of the experience worlds can be found in this folder.   

`utilities`  
  : Technical necessities used across the content and across all domain concepts.   

`base.html.twig`  
  : Base page layout of the storefront. This file mainly includes header and footer templates from `/layout` 
  and provides blocks for the `/page` templates to  overwrite.

### Page templates

The page directory contains the entry points of the templating system. These are referenced by page controllers 
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

Inside of the directories are the actual templates rendered by the storefront. The inner structure is dependant 
on the complexity of the domain context, therefore a system can not be clearly inferred from here on.

### Template multi inheritance

Due to the plugin and theme system in shopware it is possible that one storefront template gets extended by 
multiple plugins or themes, but [Twig](https://twig.symfony.com/) does not allow multi inheritance out of the box. 
Therefore we created custom twig functions `sw_extends` and `sw_include`, that work like twigs 
native [`extends`](https://twig.symfony.com/doc/2.x/tags/extends.html) or 
[`include`](https://twig.symfony.com/doc/2.x/tags/include.html), except that they allow for multi inheritance. 
So it is really important to use the `sw_extends` and `sw_include`, instead of the native `extends` and `include`. 
You can find more details about `sw_extends`and `sw_include` in following paragraphs.

#### Inheritance order

The order of the inheritance is determined by the order the plugins or themes are loaded in the plugin list 
through `bin/console plugin:refresh`.

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
on the client side. These concerns are classical enhancements as well as some Web 2.0 ajax handling. 
All scripts are written as **ES6 classes** in **vanilla JavaScript**. Additionally since bootstrap is distributed 
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

All scripting logic is written to help the plugins and keep them tidily bundled to the use case. 

## Template filters

### sw_include

If you build your own feature and you need e.g. an element to display the price of the current product you can
include existing partials with `sw_include` like this.

#### Usage

`{% sw_include '@Storefront/storefront/component/path/of/file-to-include.html.twig' %}` : Include the file's 
content 

#### Example

```twig
    <div class="my-theme an-alternative-product-view">
        ...

        {% block component_product_box_price %}
            {# use sw_include to include template partials #}
            {% sw_include '@Storefront/storefront/component/product/card/price-unit.html.twig' %}
        {% endblock %}

        ...
    </div>
```

### sw_extends

To inherit a template file, you need to use `{% sw_extends %}`.

#### Usage

{% sw_extends '@Storefront/storefront/file/you/wantToExtend.html.twig' %}` : Inherit from the given file

#### Example

```twig
{% sw_extends '@Storefront/storefront/layout/header/logo.html.twig' %}

{{ parent }}
```

### sw_sanitize

Filters tags and attributes from given variable.

The filter can be found in 
[`/src/Storefront/Framework/Twig/Extension/SwSanitizeTwigFilter.php`](https://github.com/shopware/platform/blob/master/src/Storefront/Framework/Twig/Extension/SwSanitizeTwigFilter.php)

#### Usage
`{{ unfilteredHTML|sw_sanitize }}` : Uses the default config
  
  
`{{ unfilteredHTML|sw_sanitize(mixed options = null, bool override = false) }}`

1. options: 
    - tag => attribute array that is specifically allowed
    - `*` as tag = all tags
2. override: 
    - true => uses the options as the config
    - false (default) => merges the default config with the options 

#### Examples
`{{ unfilteredHTML|sw_sanitize }}` 
  : Uses the default config
  
***

`{{ unfilteredHTML|sw_sanitize( {'div': ['style', ...]}, true ) }}`
  : **allow only** div tags + style attribute for div

`{{ unfilteredHTML|sw_sanitize( {'div': ['style', ...]} ) }}`
  : **merge** options into default config

***

`{{ unfilteredHTML|sw_sanitize( {'*': ['style', ...]}, true ) }}` 
  : **won't work** because there are no tags 

`{{ unfilteredHTML|sw_sanitize( {'*': ['style', ...]} ) }}` 
  : **merge** options into default config 
  
***
  
`{{ unfilteredHTML|sw_sanitize( {'div': ['class'], '*': ['style', ...]}, true ) }}`
  : **allow only** div tags + class attribute + allow style attribute for all tags
  
`{{ unfilteredHTML|sw_sanitize( {'div': ['class'], '*': ['style', ...]} ) }}`
  : **merge** options into default config
  
***

`{{ unfilteredHTML|sw_sanitize('', true) }}`
  : **special case :** filters all tags and attributes, because there are no allowed tags or attributes 
    - override => true, empty options array
