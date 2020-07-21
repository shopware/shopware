[titleEn]: <>(SalesChannel-API product endpoint)
[hash]: <>(article:api_sales_channel_product)

The product endpoint of the SalesChannel-API is used to get product information e.g. for a listing.

## Listing of products

**GET /sales-channel-api/v3/product**

Description: Returns a list of products assigned to the sales channel.
All filter, sorting, limit, and search operations are supported.
You find more information about these operations [here](./../3-api/050-filter-search-limit.md).

## Detailed product information

**GET /sales-channel-api/v3/product/{productId}**

Description: Returns detailed information about a specific product.

## Load associations

**GET /sales-channel-api/v3/product/{productId}?associations[media][]**

You can also load deep associations by providing multiple associations keys.

**GET /sales-channel-api/v3/product/{productId}?associations[categories][associations][media][]**

This will load the category association and the media association of the category.     

Description: Not every association of an entity is loaded by default.
If you are missing an association like the product images, just add them like shown in the example above
