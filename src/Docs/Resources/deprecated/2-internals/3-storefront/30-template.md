[titleEn]: <>(Template structure)
[hash]: <>(article:storefront_template)

The storefront theme is implemented as a skin on top of the [Boostrap toolkit](https://getbootstrap.com/). Therefore the template structure is a derivate of the [bootstrap starter template](https://getbootstrap.com/docs/4.3/getting-started/introduction/#starter-template). 
The templating engine used is [Twig](https://twig.symfony.com/).
For styling [SASS](https://sass-lang.com/) is used as CSS preprocessor.
The bundling and transpiling of the javascript [Webpack](https://webpack.js.org/) is used.

The templates can be found in [`/src/Storefront/Resources/views/storefront/`](https://github.com/shopware/platform/tree/master/src/Storefront/Resources/views) 

## Template Top Level 

```
<platform/src/Storefront/Resources/views/storefront/>
└── block
└── component
└── element
└── layout
└── page
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
  : The concrete templates rendered by the page controllers. This directory contains full page templates as well as private local includes and the pagelet ajax response templates if necessary.  

`utilities`  
  : Technical necessities used across the content, header and footer sections across all domain concepts.   

`base.html.twig`  
  : Base page layout of the storefront. This file mainly includes header and footer templates from `/layout` and provides blocks for the `/page` templates to  overwrite.

### Page templates

The page directory contains the entry points of the templating system. These are referenced by page controllers and rendered through the Twig engine. The structure is derived from the [page controller](https://github.com/shopware/platform/tree/master/src/Storefront/PageController) naming.

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

Inside of the directories are the actual templates rendered by the storefront. The inner structure is dependant on the complexity of the domain context, therefore a system can not be clearly inferred from here on.

### Template multi inheritance

Due to the plugin and theme system in shopware it is possible that one storefront template gets extended by multiple plugins or themes, but [Twig](https://twig.symfony.com/) does not allow multi inheritance out of the box. 
Therefore we created our own twig functions `sw_extends` and `sw_include`, that work exactly like twigs native [`extends`](https://twig.symfony.com/doc/2.x/tags/extends.html) or [`include`](https://twig.symfony.com/doc/2.x/tags/include.html), except that they allow for multi inheritance. 
So it is really important to use the `sw_extends` and `sw_include`, instead of the native `extends` and `include`.

#### Inheritance order

The order of the inheritance is determined by the order the plugins or themes are loaded in the plugin list through `bin/console plugin:refresh`.

## Styles Top Level

The style sheets are written in SASS. The organization is inspired by the [7-1 pattern](https://sass-guidelin.es/#architecture) structure. 

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

The `base.scss` is the global include file which references styles that are written as an extension of the bootstrap base. For further information just take a look at the excellent description at [sass-guidelines.es](https://sass-guidelin.es/#architecture).


## Scripts Top Level

The storefront includes a set of small plugins handling singular use cases on top of the storefronts templates on the client side. These concerns are classical enhancements as well as some Web 2.0 ajax handling. All scripts are written as **ES6 classes** in **vanilla JavaScript**. Additionally since bootstrap is distributed with the [jQuery library](https://jquery.com/) the storefront also contains this library.

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



