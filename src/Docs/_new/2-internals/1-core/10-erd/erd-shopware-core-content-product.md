[titleEn]: <>(Products)

Central product representation. Contains products and variations based on configuration.

![Products](./dist/erm-shopware-core-content-product.svg)


### Table `product`

A rich domain model representing single products or its variants. This is done through relations, so a root product is related to its variants through a foreign key.


### Table `product_configurator`

Association from a root product to a configuration set. Used to generate variants and surcharge or discount the price.


### Table `product_price`

Different product prices based on rules.


### Table `product_manufacturer`

The product manufacturer list.


### Table `product_media`

Relates products to media items, usually images.


### Table `product_visibility`

Set the visibility of a single product in a sales channel


