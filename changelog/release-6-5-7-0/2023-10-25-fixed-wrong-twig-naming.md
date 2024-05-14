---
title: Fixed wrong twig naming
issue: NEXT-31087
author: Florian Keller
author_email: f.keller@shopware.com

---
# Storefront
* Changed `Resources/views/storefront/component/line-item/type/product.html.twig` and added new block and class to fix naming.
* Changed `Resources/views/storefront/component/line-item/type/generic.html.twig` and added new block and class to fix naming.
* Changed `Resources/app/storefront/src/scss/component/_line-item.scss and added` new class to fix block naming.
* Deprecated block `component_line_item_type_product_order_number` in `Resources/views/storefront/component/line-item/type/product.html.twig`. Use block `component_line_item_type_product_number` instead.
* Deprecated block `component_line_item_type_generic_order_number` in `Resources/views/storefront/component/line-item/type/generic.html.twig`. Use block `component_line_item_type_generic_product_number` instead.
