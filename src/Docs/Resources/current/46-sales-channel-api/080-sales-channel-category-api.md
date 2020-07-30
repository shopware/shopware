[titleEn]: <>(SalesChannel-API category endpoint)
[hash]: <>(article:api_sales_channel_category)

The category endpoint is used to get category information e.g. for a navigation.

## Listing of categories

**GET /sales-channel-api/v3/category**

Description: Returns a list of categories assigned to the sales channel.
All filter, sorting, limit and search operations are supported.
You find more information about these operations [here](./../60-references-internals/10-core/130-dal.md).

## Detailed category information

**GET /sales-channel-api/v3/category/{categoryId}**

Description: Returns detailed information about a specific category.
