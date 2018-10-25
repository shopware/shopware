[wikiUrl]: <>(https://docs.shopware.com/en/shopware-playground-en/using-the-storefront-api/category-endpoint?category=shopware-platform-en/using-the-storefront-api)
[titleEn]: <>(['Category endpoint'])
The category endpoint is used to get category information e.g. for a
navigation.

## Listing of categories

**GET /storefront-api/category**

Description: Returns a list of categories assigned to the sales channel.
All filter, sorting, limit and search operations are supported. You find
more information about these operations
[here](../30-api/50-filter-search-limit.md).

## Detailed category information

**GET /storefront-api/category/{categoryId}**

Description: Returns detailed information about a specific category.
