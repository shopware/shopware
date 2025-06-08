---
title: New nested line items
issue: NEXT-19997
author: Tobias Berge
author_email: t.berge@shopware.com
author_github: @tobiasberge
---
# Storefront
* Deprecated the following twig template files for line-items. Use `Resources/views/storefront/component/line-item/line-item.html.twig` instead:
    * Deprecated `Resources/views/storefront/page/checkout/checkout-item.html.twig`
    * Deprecated `Resources/views/storefront/page/checkout/checkout-item-children.html.twig`
    * Deprecated `Resources/views/storefront/page/checkout/confirm/confirm-item.html.twig`
    * Deprecated `Resources/views/storefront/page/checkout/finish/finish-item.html.twig`
    * Deprecated `Resources/views/storefront/component/checkout/offcanvas-item.html.twig`
    * Deprecated `Resources/views/storefront/component/checkout/offcanvas-item-children.html.twig`
    * Deprecated `Resources/views/storefront/page/account/order/line-item.html.twig`
    * Deprecated `Resources/views/storefront/page/account/order-history/order-detail-list-item.html.twig`
    * Deprecated `Resources/views/storefront/page/account/order-history/order-detail-list-item-children.html.twig`
    * Deprecated `Resources/views/storefront/page/checkout/checkout-aside-item.html.twig`
    * Deprecated `Resources/views/storefront/page/checkout/checkout-aside-item-children.html.twig`
* Deprecated the following twig templates for cart table headers. Use `Resources/views/storefront/component/checkout/cart-header.html.twig` instead:
    * Deprecated `Resources/views/storefront/page/checkout/cart/cart-product-header.html.twig`
    * Deprecated `Resources/views/storefront/page/checkout/confirm/confirm-product-header.html.twig`
    * Deprecated `Resources/views/storefront/page/account/order/line-item-header.html.twig`
* Deprecated the following SCSS files. Use `Resources/app/storefront/src/scss/component/_line-item.scss` instead:
  * Deprecated `Resources/app/storefront/src/scss/page/checkout/_cart-item.scss`
  * Deprecated `Resources/app/storefront/src/scss/page/checkout/_cart-item-children.scss`
  * Deprecated `Resources/app/storefront/src/scss/skin/page/checkout/_cart-item.scss`
___
# Upgrade Information

## Refactoring of storefront line item twig templates

With the next major release we want to unify the twig templates, which are used to display line items in the storefront.
Right now, there are multiple different templates for different areas in which line items are displayed:
* Cart, confirm and finish page
* OffCanvas Cart
* Account order details

Those different templates will be removed in favor of a new line item base template, which can be adjusted via configuration variables.
Furthermore, each known line item type will have its own sub-template to avoid too many if/else conditions within the line item base template.
This will also future-proof the line item base template for possible new line item types. 
There will be no more separate `-children` templates for nested line items. Nested line items will also be covered by the new base template.

* New line item template: `Resources/views/storefront/component/line-item/line-item.html.twig`
    * Config variables:
        * `displayMode` (string) - Toggle the appearance of the line item
            * `default` - Full line item appearance including mobile and desktop styling
            * `offcanvas` - Appearance will always stay mobile, regardless of the viewport size. Provides additional classes for OffCanvas JS-plugins
            * `order` - Appearance for display inside the account order list
        * `showTaxPrice` (boolean) - Show the tax price instead of the unit price of the line item.
        * `showQuantitySelect` (boolean) - Show a select dropdown to change the quantity. When false it only displays the current quantity as text.
        * `redirectTo` (string) - The redirect route, which should be used after performing actions like "remove" or "change quantity".
    * types:
        * `product` - Display a product line item including preview image, additional information and link to product.
        * `discount` - Display a discount line item and skip all unneeded information like "variants".
        * `container` - Display a container line item, which can include nested line items.
        * `generic` - Display a line item with an unknown type, try to render as much information as possible.
___
# Next Major Version Changes

## Overwrite or extend line item templates:

If you are extending line item templates inside the cart, OffCanvas or other areas, you need to use the line item base template `Resources/views/storefront/component/line-item/line-item.html.twig`
and extend from one of the template files inside the `Resources/views/storefront/component/line-item/types/` directory.

For example: You extend the line item's information about product variants with additional content.

### Before
```twig
{# YourExtension/src/Resources/views/storefront/page/checkout/checkout-item.html.twig #}

{% sw_extends '@Storefront/storefront/page/checkout/checkout-item.html.twig' %}

{% block page_checkout_item_info_variant_characteristics %}
    {{ parent() }}
    <div>My extra content</div>
{% endblock %}
```

### After
```twig
{# YourExtension/src/Resources/views/storefront/component/line-item/type/product.html.twig #}

{% sw_extends '@Storefront/storefront/component/line-item/type/product.html.twig' %}

{% block component_line_item_type_product_variant_characteristics %}
    {{ parent() }}
    <div>My extra content</div>
{% endblock %}
```

Since the new `line-item.html.twig` is used throughout multiple areas, the template extension above will take effect for product line items
in all areas. Depending on your use case, you might want to restrict this to more specific areas. You have the possibility to check the
current `displayMode` to determine if the line item is shown inside the OffCanvas for example. Previously, the OffCanvas line items had
an individual template. You can now use the same `line-item.html.twig` template as for regular line items.

### Before
```twig
{# YourExtension/src/Resources/views/storefront/component/checkout/offcanvas-item.html.twig #}

{% sw_extends '@Storefront/storefront/component/checkout/offcanvas-item.html.twig' %}

{% block cart_item_variant_characteristics %}
    {{ parent() }}
    <div>My extra content</div>
{% endblock %}
```

### After
```twig
{# YourExtension/src/Resources/views/storefront/component/line-item/type/product.html.twig #}

{% sw_extends '@Storefront/storefront/component/line-item/type/product.html.twig' %}

{% block component_line_item_type_product_variant_characteristics %}
    {{ parent() }}

    {# Only show content when line item is inside offcanvas #}
    {% if displayMode === 'offcanvas' %}
        <div>My extra content</div>
    {% endif %}
{% endblock %}
```

You can narrow down this even more by checking for the `controllerAction` and render your changes only in desired actions.
The dedicated `confirm-item.html.twig` in the example below no longer exists. You can use `line-item.html.twig` as well.

### Before
```twig
{# YourExtension/src/Resources/views/storefront/page/checkout/confirm/confirm-item.html.twig #}

{% sw_extends '@Storefront/storefront/page/checkout/confirm/confirm-item.html.twig' %}

{% block cart_item_variant_characteristics %}
    {{ parent() }}
    <div>My extra content</div>
{% endblock %}
```

### After
```twig
{# YourExtension/src/Resources/views/storefront/component/line-item/type/product.html.twig #}

{% sw_extends '@Storefront/storefront/component/line-item/type/product.html.twig' %}

{% block component_line_item_type_product_variant_characteristics %}
    {{ parent() }}

    {# Only show content on the confirm page #}
    {% if controllerAction === 'confirmPage' %}
        <div>My extra content</div>
    {% endif %}
{% endblock %}
```
