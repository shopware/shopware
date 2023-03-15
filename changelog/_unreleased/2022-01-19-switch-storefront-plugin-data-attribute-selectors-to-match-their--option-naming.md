---
title: Switch storefront plugin data-attribute selectors to match their -option naming
issue: NEXT-19709
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Storefront
* Deprecated several data-attribute selectors to better match their corresponding JavaScript plugin names to improve developer experience when using JavaScript plugin configurations
    * Deprecated JavaScript plugin selector `data-search-form`. Use `data-search-widget` instead
    * Deprecated JavaScript plugin selector `data-offcanvas-cart`. Use `data-off-canvas-cart` instead
    * Deprecated JavaScript plugin selector `data-collapse-footer`. Use `data-collapse-footer-columns` instead
    * Deprecated JavaScript plugin selector `data-offcanvas-menu`. Use `data-off-canvas-menu` instead
    * Deprecated JavaScript plugin selector `data-offcanvas-account-menu`. Use `data-account-menu` instead
    * Deprecated JavaScript plugin selector `data-offcanvas-tabs`. Use `data-off-canvas-tabs` instead
    * Deprecated JavaScript plugin selector `data-offcanvas-filter`. Use `data-off-canvas-filter` instead
    * Deprecated JavaScript plugin selector `data-offcanvas-filter-content`. Use `data-off-canvas-filter-content` instead
___
# Upgrade Information

## Changes to data-attribute selector names

We want to change several data-attribute selector names to be more aligned with the JavaScript plugin name which is initialized on the data-attribute selector.
When you use one of the selectors listed below inside HTML/Twig, JavaScript or CSS, please change the selector to the new selector.

## HTML/Twig example

### Before

```twig
<div 
    data-offcanvas-menu="true" {# <<< Did not match options attr #}
    data-off-canvas-menu-options='{ ... }'
>
</div>
```

### After

```twig
<div 
    data-off-canvas-menu="true" {# <<< Now matches options attr #}
    data-off-canvas-menu-options='{ ... }'
>
</div>
```

_The options attribute is automatically generated using the camelCase JavaScript plugin name._

## Full list of selectors

| old                             | new                              |
|:--------------------------------|:---------------------------------|
| `data-search-form`              | `data-search-widget`             |
| `data-offcanvas-cart`           | `data-off-canvas-cart`           |
| `data-collapse-footer`          | `data-collapse-footer-columns`   |
| `data-offcanvas-menu`           | `data-off-canvas-menu`           |
| `data-offcanvas-account-menu`   | `data-account-menu`              |
| `data-offcanvas-tabs`           | `data-off-canvas-tabs`           |
| `data-offcanvas-filter`         | `data-off-canvas-filter`         |
| `data-offcanvas-filter-content` | `data-off-canvas-filter-content` |

