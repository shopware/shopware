[titleEn]: <>(API)
[titleDe]: <>(API)
[hash]: <>(category:api)

## Overview

The Admin API can be used to complete all administrative tasks, like creating products, updating prices and much more.
For building a storefront or extending it, you can use the [SalesChannel-API](./060-sales-channel-api.md).

The Admin API makes it really easy to integrate *Shopware* into your environment.

## Versioning

The Admin API is versioned by adding a version string to the query path. Currently, the only available version is **v1**.

## Schema overview

All HTTP methods follow the usual REST behavior.
**GET** on a resource retrieves the resource.
**POST** adds a new resource. **PATCH** updates some attributes of a resource.
**DELETE** removes a resource.

The resources are all named in singular. Only the 1-n and n-m relation names of sub-resources are pluralized.
For example: **/api/v3/category** and **/api/v3/category/products**.

Some examples:

| Method | Example                           | Description                                                  |
| ------ | --------------------------------- | ------------------------------------------------------------ |
| GET    | /api/v3/category                  | Get a list of categories                                     |
| GET    | /api/v3/category/01bd7e7...       | Get the details of the category with id "01bd7e7..."         |
| POST   | /api/v3/category                  | Add a new category                                           |
| PATCH  | /api/v3/category/01bd7e7...       | Update the category with id "01bd7e7..."                     |
| DELETE | /api/v3/category/01bd7e7...       | Delete the category with id "01bd7e7..."                     |
| GET    | /api/v3/category/01bd.../products | Get the list of products belonging to the category "01bd..." |

### Sub-resources

All relationships are exposed as sub-resources. Only the **GET** method is allowed for sub-resources.

### Identifier

All identifiers are [UUIDs Version 4](https://en.wikipedia.org/wiki/Universally_unique_identifier#Version_4_\(random\)).
You can generate the UUIDs yourself or let the API backend generate them for you by leaving them out in the request body.
