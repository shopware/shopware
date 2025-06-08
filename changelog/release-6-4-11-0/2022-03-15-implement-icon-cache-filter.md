---
title: Fix html and implement icon cache filter
issue: NEXT-18411
author: Stefan Sluiter
author_email: s.sluiter@shopware.com
---
# Storefront
* Added `Shopware\Storefront\Framework\Twig\Extension\IconCacheTwigFilter` to add every icon definition only once per page.
* Changed all icons in `Storefront/Resources/app/storefront/dist/assets/icon/*`. Removed `fill` attribute and added `defs`.
* Added `Shopware\Storefront\Theme\Command\ThemePrepareIconsCommand` to prepare icons for storefront usage.
* Changed `Shopware\Storefront\Controller\StorefrontController::renderStorefront` by enabling `IconCacheTwigFilter`.
* Added new dependency `meyfa/php-svg`.
* Changed `Storefront/Resources/views/storefront/utilities/icon.html.twig` to add `sw_icon_cache` filter to icon implementation.
* Changed `Storefront/Resources/views/storefront/layout/meta.html.twig` to only render `<link rel="apple-touch-icon">` when `sw-logo-share` is not empty.
* Changed `Storefront/Resources/views/storefront/layout/footer/footer.html.twig`. Added `role=list` attribute to `footerColumns` and `role=listitem` attribute to `collapseFooterHotlineTitle`.
* Deprecated span in `Storefront/Resources/views/storefront/component/review/rating.html.twig`. Changed `span` `product-review-rating` to `div`
* Deprecated span in `Storefront/Resources/views/storefront/component/review/point.html.twig`. Changed `span` `product-review-point` to `div`
* Deprecated label in `Storefront/Resources/views/storefront/component/listing/filter/filter-rating-select-item.html.twig`. Moved `label` `filter-rating-select-item-label` above block `component_filter_rating_select_list_item_label_rating`.

___
# Upgrade Information

## New Twig filter sw_icon_cache
From now on, all icons implemented via `sw_icon` is wrapped with `sw_icon_cache`. 
This causes all icons only be defined once per html page and multiple occurences be referenced by id.
### Example
First implementation of the `star` icon:
```html
<svg xmlns="http://www.w3.org/2000/svg" 
     xmlns:xlink="http://www.w3.org/1999/xlink" 
     width="24" height="24" viewBox="0 0 24 24">
    <defs>
        <path id="icons-solid-star" 
              d="M6.7998 23.3169c-1.0108.4454-2.1912-.0129-2.6367-1.0237a2 2 0 0 1-.1596-1.008l.5724-5.6537L.7896 11.394c-.736-.8237-.6648-2.088.1588-2.824a2 2 0 0 1 .9093-.4633l5.554-1.2027 2.86-4.9104c.556-.9545 1.7804-1.2776 2.7349-.7217a2 2 0 0 1 .7216.7217l2.86 4.9104 5.554 1.2027c1.0796.2338 1.7652 1.2984 1.5314 2.378a2 2 0 0 1-.4633.9093l-3.7863 4.2375.5724 5.6538c.1113 1.0989-.6894 2.08-1.7883 2.1912a2 2 0 0 1-1.008-.1596L12 21.0254l-5.2002 2.2915z">
        </path>
    </defs>
    <use xlink:href="#icons-solid-star"></use>
</svg>
```
Following implementations of the `star` icon:
```html
<svg xmlns="http://www.w3.org/2000/svg" 
     xmlns:xlink="http://www.w3.org/1999/xlink" 
     width="24" height="24" viewBox="0 0 24 24">
    <use xlink:href="#icons-solid-star"></use>
</svg>
```
This behaviour can be disabled by setting the system config `core.storefrontSettings.iconCache` to `false`.
The Setting can be found in the administration under `Settings`-> `System`->`Storefront`->`Activate icon cache`
From 6.5.0.0 on this will be enabled by default.

You can enable and disable this behaviour on a template basis by calling the new twig function `sw_icon_cache_enable`
and `sw_icon_cache_disable`.

## New Command theme:prepare-icons
The new command `theme:prepare-icons` prepares svg icons for usage in the storefront with compatibility with the icon cache.
The command requires a path for the icons to prepare and a package name for the icons and will save all updated icons to a subdirectory `prepared`.
Optional you can also set the following options:
* --cleanup (true|false) - This will remove all unnecessary attributes from the icons.
* --fillcolor (color) - This will add this colorcode to the `fill` attribute.
* --fillrule (svg fill rule) - This will add the fill rule to the `fill-rule` attribute
```
/bin/console theme:prepare-icons /app/platform/src/Storefront/Resources/app/storefront/dist/assets/icon/default/ default -c true -r evenodd -f #12ef21
``` 