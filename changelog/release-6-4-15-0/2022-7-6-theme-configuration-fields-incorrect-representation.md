---
title: Theme configuration - config fields incorrect representation
issue: NEXT-20062
---
# Administration
* Changed in `src/Storefront/Resources/app/administration/src/modules/sw-theme-manager/page/sw-theme-manager-detail/sw-theme-manager-detail.html.twig`
    * Added a bound attribute `helpText` to `sw-inherit-wrapper` to render a help text if it is the checkbox or the switch
    * Added a classname `sw-inherit-wrapper-switch` or `sw-inherit-wrapper-checkbox` depends on the type of field
    
* Changed in `src/Storefront/Resources/app/administration/src/modules/sw-theme-manager/page/sw-theme-manager-detail/sw-theme-manager-detail.scss`
    * Added the specific style for the checkbox or the switch field
