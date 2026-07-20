---
title: Extract add product by number checkout cart
flag: V6_8_0_0
author: Sebastian Seggewiss
author_github: @seggewiss
---
# Storefront
* Added `Resources/views/storefront/component/checkout/add-product-by-number.html.twig`
* Deprecated the following Twig blocks in `Resources/views/storefront/page/checkout/cart/index.html.twig`
  * `page_checkout_cart_add_product`
  * `page_checkout_cart_add_product_redirect`
  * `page_checkout_cart_add_product_label`
  * `page_checkout_cart_add_product_input_group`
  * `page_checkout_cart_add_product_input`
  * `page_checkout_cart_add_product_submit`
___
# Upgrade Information
## Changing cart add-product twig blocks
Instead of overwriting any of the `page_checkout_cart_add_product*` blocks inside `@Storefront/storefront/page/checkout/cart/index.html.twig`,
extend the new `@Storefront/storefront/component/checkout/add-product-by-number.html.twig` file using the same blocks.
___
# Next Major Version Changes
## Changing cart add-product twig blocks:
Change:
```
{% sw_extends '@Storefront/storefront/page/checkout/_page.html.twig' %}

{% block page_checkout_cart_add_product %}
    {# Your content #}
{% endblock %}
```
to:
```
{% sw_extends '@Storefront/storefront/component/checkout/add-product-by-number.html.twig' %}

{% block page_checkout_cart_add_product %}
    {# Your content #}
{% endblock %}
```
