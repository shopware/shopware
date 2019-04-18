[titleEn]: <>(SalesChannel-API product endpoint)

The product endpoint of the SalesChannel-API is used to get product information e.g. for a listing.

## Listing of products

**GET /sales-channel-api/v1/product**

Description: Returns a list of products assigned to the sales channel.
All filter, sorting, limit, and search operations are supported.
You find more information about these operations [here](./../3-api/50-filter-search-limit.md).

## Detailed product information

**GET /sales-channel-api/v1/product/{productId}**

Description: Returns detailed information about a specific product.
