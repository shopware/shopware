[titleEn]: <>(Template structure)

The storefronts theme is implemented as a skin on top of the [Boostrap toolkit](https://getbootstrap.com/). Therefore the template structure is a derivate of the [bootstrap starter template](https://getbootstrap.com/docs/4.3/getting-started/introduction/#starter-template). The templating engine used is [Twig](https://twig.symfony.com/)

The templates can be found in [`/src/Storefront/Resources/views`](https://github.com/shopware/platform/tree/master/src/Storefront/Resources/views) 

## Template Top Level 

```
<platform/src/Storefront/Resources/views>
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
<platform/src/Storefront/Resources/views/page>
└── account
└── checkout
└── content
└── error
└── newsletter
└── product-detail
└── search
```

Inside of the directories are the actuial templates rendered by the storefront. The inner structure is dependant on the complexity of the domain context, therefore a system can not be clearly inferred from here on.

## Styles Top Level

The style sheets are written in SASS. The organization is inspired by the [7-1 pattern](https://sass-guidelin.es/#architecture) structure. 

```
<platform/src/Storefront/Resources/src/style>
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
<platform/src/Storefront/Resources/src/script>
└── config
└── helper
└── plugin
└── service
└── utility
└── vendor
└── base.js
```

All scripting logic is written to help the plugins and keep them tidily bundled to the use case. 



