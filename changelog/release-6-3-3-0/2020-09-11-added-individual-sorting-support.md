---
title:              Added individual sorting support
issue:              NEXT-9457
author:             Lennart Tinkloh
author_email:       l.tinkloh@shopware.com
author_github:      lernhart
---
# Core
* Added new `Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity` to handle individual sortings stored in database
* Added new `\Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingTranslationEntity` to handle translated labels
* Deprecated `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSortingRegistry` for v6.4.0.0 . 
  Sortings are now stored in database, rather than declaring them as services
* Deprecated `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSorting` for v6.4.0.0 .
  Use `\Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity` instead
___
# Storefront
* Changed `src/Storefront/Resources/views/storefront/component/sorting.html.twig` to work with the new sorting options logic
___
# Upgrade Information

## Deprecation of the current sortings implementation

The current defined sortings in the service definition xml are deprecated for release **6.4.0.0** .

If you have defined custom sorting options in the service definition, please consider upgrading to the new logic via migration.

Before, custom sortings were handled by defining them as services and tagging them as `shopware.sales_channel.product_listing.sorting`:
```xml
<service id="product_listing.sorting.name_ascending" class="Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSorting">
    <argument>name-asc</argument>
    <argument>filter.sortByNameAscending</argument>
    <argument type="collection">
        <argument key="product.name">asc</argument>
    </argument>
    <tag name="shopware.sales_channel.product_listing.sorting" />
</service>
```
Now it is possible to store custom sortings in the database `product_sorting` and its translatable label in `product_sorting_translation`
