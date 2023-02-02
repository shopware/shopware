[titleEn]: <>(Products)
[hash]: <>(article:internals_core_erd_content_product)

[Back to modules](./../10-modules.md)

Central product representation. Contains products and variations based on configuration.

![Products](./dist/erd-shopware-core-content-product.png)


### Table `product`

A rich domain model representing single products or its variants.
This is done through relations, so a root product is related to its variants through a foreign key.


### Table `product_configurator_setting`

Association from a root product to a configuration set. Used to generate variants and surcharge or discount the price.


### Table `product_price`

Different product prices based on rules.


### Table `product_search_keyword`

SQL based product search table, containing the keywords.


### Table `product_keyword_dictionary`

SQL based product search table containing the dictionary.


### Table `product_review`

__EMPTY__


### Table `product_manufacturer`

The product manufacturer list.


### Table `product_media`

Relates products to media items, usually images.


### Table `product_cross_selling`

__EMPTY__


### Table `product_cross_selling_assigned_products`

__EMPTY__


### Table `product_feature_set`

__EMPTY__


### Table `product_sorting`

Provides functionality to define sorting groups to sort products by.


### Table `product_search_config`

__EMPTY__


### Table `product_search_config_field`

__EMPTY__


### Table `product_visibility`

Set the visibility of a single product in a sales channel


[Back to modules](./../10-modules.md)
