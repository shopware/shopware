---
title: Navbar for main navigation
issue: NEXT-36185
flag: V6_7_0_0
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: BrocksiNet
---
# Storefront
* Added default bootstrap navbar for main navigation
  * Add `V6_7_0_0=1` to you `.env` file to test the new navbar
  * The new navbar support aria attributes for accessibility
  * The new navbar works without custom css
  * The new navbar plugin is much smaller than the old one
  * It has all the features of the old flyout menu has
  * For more details see also Dropdowns in the [Bootstrap documentation](https://getbootstrap.com/docs/5.2/components/dropdowns/)
___
# Next Major Version Changes

The following files inside `src/Storefront/Resources/` will be **removed in 6.7**
* `views/storefront/layout/navigation/navigation.html.twig`  
  (replacement `views/storefront/layout/navbar/navbar.html.twig`)
* `views/storefront/layout/navigation/categories.html.twig`  
  (replacement `views/storefront/layout/navbar/categories.html.twig`)
* `views/storefront/layout/navigation/flyout.html.twig`  
  (replacement `views/storefront/layout/navbar/content.html.twig`)
* `app/storefront/src/plugin/main-menu/flyout-menu.plugin.js`  
  (replacement `app/storefront/src/plugin/navbar/navbar.plugin.js`)
* `app/storefront/src/scss/layout/_main-navigation.scss`  
  (no replacement)
