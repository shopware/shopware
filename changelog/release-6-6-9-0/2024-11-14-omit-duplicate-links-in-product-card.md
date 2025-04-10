---
title: Omit duplicate links in product box
issue: NEXT-39104
---
# Storefront
* Deprecated wrapper link `.product-image-link` in `Resources/views/storefront/component/product/card/box-standard.html.twig`. The product name will be used as primary link and its click surface will be stretched over the image using `stretched-link`.
* Deprecated block `component_product_box_image_link`. User inner block `component_product_box_image_link_inner` instead.
___
# Upgrade Information
## Storefront product box accessibility: Replace duplicate links around the product image with stretched link in product name
**Affected template: `Resources/views/storefront/component/product/card/box-standard.html.twig`**

Currently, the link to the product detail page is always duplicated in the default product box because the image is wrapped with the same link.
This is not ideal for accessibility because the link is read twice when using a screen reader. Therefore, we want to remove the link around the product image that also points to the detail page.
To make the image still click-able the Bootstrap helper class `stretched-link` will be used on the product name link.

When the `ACESSIBILITY_TWEAKS` flag is active, the product card will no longer contain a link around the product image:
```diff
<div class="card product-box box-standard">
    <div class="card-body">
        <div class="product-image-wrapper">
-            <a href="https://shopware.local/Example-Product/SW-01931a101dcc725aa3affc0ff408ee31">
                <img src="https://shopware.local/media/a3/22/75/1731309077/Example-Product_%283%29.webp?ts=1731309077" alt="Example-Product">
-            </a>
        </div>

        <div class="product-info">
            <a href="https://shopware.local/Example-Product/SW-01931a101dcc725aa3affc0ff408ee31"
+               class="product-name stretched-link"> {# <------ stretched-link is used instead #}
                Example-Product
            </a>
        </div>
    </div>
</div>
```
___
# Next Major Version Changes
## Storefront product box accessibility: Removed duplicate links around the product image in product cards
**Affected template: `Resources/views/storefront/component/product/card/box-standard.html.twig`**

The anchor link around the product image `a.product-image-link` is removed and replaced with the link of the product name `a.product-name` that now uses the `stretched-link` helper class:
```diff
<div class="card product-box box-standard">
    <div class="card-body">
        <div class="product-image-wrapper">
-            <a href="https://shopware.local/Example-Product/SW-01931a101dcc725aa3affc0ff408ee31">
                <img src="https://shopware.local/media/a3/22/75/1731309077/Example-Product_%283%29.webp?ts=1731309077" alt="Example-Product">
-            </a>
        </div>

        <div class="product-info">
            <a href="https://shopware.local/Example-Product/SW-01931a101dcc725aa3affc0ff408ee31"
+               class="product-name stretched-link"> {# <------ stretched-link is used instead #}
                Example-Product
            </a>
        </div>
    </div>
</div>
```