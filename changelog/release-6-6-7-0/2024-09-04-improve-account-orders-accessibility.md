---
title: Improve account orders accessibility
issue: NEXT-38170
---
# Storefront
* Changed `order-wrapper` element from `<div>` to `<article>` in `Resources/views/storefront/page/account/order-history/order-item.html.twig`
* Changed `account-aside-list-group` element from `<div>` to `nav` in `Resources/views/storefront/page/account/sidebar.html.twig`
* Deprecated custom styling for `order-table-header-context-menu`. Bootstrap class `btn-light` will be used instead.
* Deprecated custom styling for `order-table-header-context-menu-content-link`. Bootstrap class `dropdown-item` will be used instead.
* Deprecated using `<div>` elements for list items and list wrappers in the following templates. Actual lists `<ul class="list-unstyled">` and `<li>` will be used instead:
    * `Resources/views/storefront/page/account/order-history/index.html.twig`
    * `Resources/views/storefront/page/account/order-history/order-detail-document-item.html.twig`
    * `Resources/views/storefront/page/account/order-history/order-detail-document.html.twig`
    * `Resources/views/storefront/page/account/order-history/order-detail-list.html.twig`
    * `Resources/views/storefront/page/account/order-history/order-detail-list.html.twig`
    * `Resources/views/storefront/page/checkout/cart/index.html.twig`
    * `Resources/views/storefront/page/checkout/confirm/index.html.twig`
    * `Resources/views/storefront/page/checkout/finish/index.html.twig`
    * `Resources/views/storefront/page/checkout/address/index.html.twig`
    * `Resources/views/storefront/component/checkout/offcanvas-cart.html.twig`
    * `Resources/views/storefront/component/line-item/type/product.html.twig`
    * `Resources/views/storefront/component/line-item/type/discount.html.twig`
    * `Resources/views/storefront/component/line-item/type/generic.html.twig`
    * `Resources/views/storefront/component/line-item/type/container.html.twig`
___
# Upgrade Information
## Change Storefront order items and cart line-items from `<div>` to `<ul>` and `<li>`:
* We want to change several list views that are currently using generic `<div>` elements to proper `<ul>` and `<li>`. This will not only improve the semantics but also the screen reader accessibility. 
* To avoid breaking changes in the HTML and the styling, the change to `<ul>` and `<li>` is done behind the `ACCESSIBILITY_TWEAKS` feature flag.
* With the next major version the `<ul>` and `<li>` will become the default. In the meantime, the `<div>` elements get `role="list"` and `role="listitem"`.
* All `<ul>` will get a Bootstrap `list-unstyled` class to avoid the list bullet points and have the same appearance as `<div>`.
* The general HTML structure and Twig blocks remain the same.

### Affected templates:
* Account order overview
    * `src/Storefront/Resources/views/storefront/page/account/order-history/index.html.twig`
    * `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-document-item.html.twig`
    * `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-document.html.twig`
* Cart table header (Root element changed to `<li>`)
    * `src/Storefront/Resources/views/storefront/component/checkout/cart-header.html.twig`
* Line-items wrapper (List wrapper element changed to `<ul>`)
    * `src/Storefront/Resources/views/storefront/page/checkout/cart/index.html.twig`
    * `src/Storefront/Resources/views/storefront/page/checkout/confirm/index.html.twig`
    * `src/Storefront/Resources/views/storefront/page/checkout/finish/index.html.twig`
    * `src/Storefront/Resources/views/storefront/page/checkout/address/index.html.twig`
    * `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-list.html.twig`
    * `src/Storefront/Resources/views/storefront/component/checkout/offcanvas-cart.html.twig`
* Line-items (Root element changed to `<li>`)
    * `src/Storefront/Resources/views/storefront/component/line-item/type/product.html.twig`
    * `src/Storefront/Resources/views/storefront/component/line-item/type/discount.html.twig`
    * `src/Storefront/Resources/views/storefront/component/line-item/type/generic.html.twig`
    * `src/Storefront/Resources/views/storefront/component/line-item/type/container.html.twig`
___

# Next Major Version Changes
## Change Storefront order items and cart line-items from `<div>` to `<ul>` and `<li>`:
To improve the accessibility and semantics, several generic `<div>` elements that are representing lists are changed to actual `<ul>` and `<li>` elements.
This effects the account order overview area as well as the cart line-item templates.

If you are adding custom line-item templates, please change the root element to an `<li>` element:

change
```twig
<div class="{{ lineItemClasses }}">
    <div class="row line-item-row">
        {# Line item content #}
    </div>
<div>
```
to
```twig
<li class="{{ lineItemClasses }}">
    <div class="row line-item-row">
        {# Line item content #}
    </div>
<li>
```

If you are looping over line-items manually in your template, please change the nearest parent element to an `<ul>`:

change
```twig
<div class="line-item-container-custom">
    {% for lineItem in lineItems %}
        {# Now renders `<li>` #}
        {% sw_include '@Storefront/storefront/component/line-item/line-item.html.twig' %}
    {% endfor %}
</div>
```
to
```twig
<ul class="line-item-container-custom list-unstyled">
    {% for lineItem in lineItems %}
        {# Now renders `<li>` #}
        {% sw_include '@Storefront/storefront/component/line-item/line-item.html.twig' %}
    {% endfor %}
</ul>
```

### List of affected templates:
Please consider the documented deprecations inside the templates and adjust modified HTML accordingly.
The overall HTML tree structure and the Twig blocks are not affected by this change.

* Account order overview
  * `src/Storefront/Resources/views/storefront/page/account/order-history/index.html.twig`
  * `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-document-item.html.twig`
  * `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-document.html.twig`
* Cart table header (Root element changed to `<li>`)
  * `src/Storefront/Resources/views/storefront/component/checkout/cart-header.html.twig`
* Line-items wrapper (List wrapper element changed to `<ul>`)
  * `src/Storefront/Resources/views/storefront/page/checkout/cart/index.html.twig`
  * `src/Storefront/Resources/views/storefront/page/checkout/confirm/index.html.twig`
  * `src/Storefront/Resources/views/storefront/page/checkout/finish/index.html.twig`
  * `src/Storefront/Resources/views/storefront/page/checkout/address/index.html.twig`
  * `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-list.html.twig`
  * `src/Storefront/Resources/views/storefront/component/checkout/offcanvas-cart.html.twig`
* Line-items (Root element changed to `<li>`)
  * `src/Storefront/Resources/views/storefront/component/line-item/type/product.html.twig`
  * `src/Storefront/Resources/views/storefront/component/line-item/type/discount.html.twig`
  * `src/Storefront/Resources/views/storefront/component/line-item/type/generic.html.twig`
  * `src/Storefront/Resources/views/storefront/component/line-item/type/container.html.twig`
