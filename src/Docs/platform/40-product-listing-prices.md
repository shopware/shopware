# Product listing prices

Like described in the **Product context prices** chapter, the product prices are stored in the `Shopware\Api\Product\Definition\ProductContextPriceDefinition` storage.
Product listing prices can be seen as **marketing price**, which means it is the cheapest price of a product which can be configured by the customer.
This configuration considers:
* different variant of the same product (children shoes, different size or color)
* graduation discounts (buy 20 units and you got 10% discount)

Considering that product prices are based on the **context rule system** it is really expensive (performance) to calculate the cheapest product price on demand in listings for sortings or aggregations.
For the sake of the storage engine (in many cases the mysql server), shopware stores pre calculates this listing prices and stores them in the `Shopware\Api\Product\Definition\ProductDefinition::listingPrices` field.

The prices are stored in mysql as json which allows a coalesce select for the different rules. The following json shows the listing prices for a single product:

```json
{
  //rule id
  "rb578c329ee5a4a6ca2090671b45d1822": {
  
    //currency id  
    "c4c8eba11bd3546d786afbed481a6e665": {
      "gross":48,
      "net":40.33613445378151
    }
  },
  //rule id
  "rd4ecc5ec828d4d7b901fe417056d01b5":{
    //currency id
    "c4c8eba11bd3546d786afbed481a6e665":{
      "gross":83,
      "net":69.74789915966387
    }
  }
}
```

In my sql it is now possible to create a coalesce select to find the first matching listing price for each product row:
```mysql
SELECT 
  coalesce(
    
    JSON_UNQUOTE(JSON_EXTRACT(product.listing_prices, "$.rb578c329ee5a4a6ca2090671b45d1822.c4c8eba11bd3546d786afbed481a6e665.gross"))
    JSON_UNQUOTE(JSON_EXTRACT(product.listing_prices, "$.rd4ecc5ec828d4d7b901fe417056d01b5.c4c8eba11bd3546d786afbed481a6e665.gross"))
    product.price.gross
  )
  #...
```

Each row of the above coalesce has the following construction:
* `rb578c329ee5a4a6ca2090671b45d1822` => 'r' + context rule id
* `c4c8eba11bd3546d786afbed481a6e665` => 'c' + currency id
* `gross` => gross or net possible

This means, the sql server first checks, per product row, if a price is defined for the rule `b578c329ee5a4a6ca2090671b45d1822`. In case a product
has no price for this context rule id, he looks into the next coalesce part with id `d4ecc5ec828d4d7b901fe417056d01b5`. In last case, the product has no matching context rule price
the sql server uses the `product.price` as fallback.
