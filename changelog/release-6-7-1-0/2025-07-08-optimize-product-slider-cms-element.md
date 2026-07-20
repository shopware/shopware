---
title: Optimize product slider CMS element
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Administration
* Deprecated `navArrowsClasses` computed property of `sw-cms-el-product-slider` component.
* Changed `has--navigation-indent` class of `sw-cms-el-product-slider` component to represent navigation position instead like `has--navigation-inside` or `has--navigation-outside`.
* Changed styles of `sw-cms-el-product-slider` component to fix overflowing border and generally improve the Storefront representation.
___
# Storefront
* Removed unnecessary `.has-nav` styling from product slider component.
* Changed handling of navigation arrows in `cms-element-product-slider.html.twig` template.
