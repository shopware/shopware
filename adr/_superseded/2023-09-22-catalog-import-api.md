---
title: Catalog Import API
date: 2023-09-06
area: core
tags: [catalog, product, category, import, api]
---
## Superseded as this "project" was aborted as a lot of further issues came up while working on this approach.

## Context

Our current API for importing entities is very flexible,
however:  

* The documentation is lackluster and brittle due to the fact that the schema maps directly to the databases.
* This exposure of implementation details leads to issues documenting the api but also using it as certain fields and entities are just bookkeeping.
* The size and complexity of the current schema make it impossible to generate clients or mappers for it.
* Variants and Media uploads have to be fully managed by the client
* The api puts the effort of error handling onto the client

Resulting in increased effort to build and maintain middlewares.

We want to provide an HTTP API and a PHP API for importing catalog related entities independent of the database schema.
And reduce the amount of implementation details developers have to know about when integrating Shopware within existing
systems e.g., ProductVisibility, ProductMedia Entities, Media creation, errors, etc.

## Decision

We will introduce a new PHP Layer for importing catalog entities. It will:

* Evolve independently of the current database schema
* Be strongly typed
* Support referencing associations by different fields (eg assign tax by name instead of id)
* Be simpler to import images and generate variants
* Support asynchronous importing
* Have advanced error handling and reporting

We will expose this new PHP layer over an HTTP API with full Open API specifications.

## Consequences

We will first release an @experimental feature with a very limited scope, a minimal data set.
We will not support the full breadth of options available to the catalog system.

This will allow us to gather feedback, make changes and provide a well-designed system.


### API Overview

**PHP api**

```php
// for the structure of $myProductData see: Appendix A
function myMigration(array $myProductData)
{

    $productBatch = ProductBatch::fromRequestData($myProductData);
    $session = $this->importFactory->startSession('my-import');
    $session->addProductRecords($productBatch);
    $session->commit();
    // not required, as import runs async
    while(!$session->done()) {
        echo $session->status();
    }
}
```

**REST api**

This API is exposed as the following HTTP endpoints:

### Starting an import 

`POST /api/import/catalog/start`:

This creates an import return a unique identifier to the import. This endpoint accepts a configuration payload, allowing you to customize the import, for example:

* The name of the import, eg `product-import-202307012`
* The indexing configuration
* Reporting webhooks for errors
* And so on

### Creating/Updating entities 

`POST /api/import/{{import_id}}/record`:

#### Examples

 * [Create a product with a new media](./assets/catalog-import/examples.md#create-a-product-with-a-new-media)
 * [Update a product and update its media](./assets/catalog-import/examples.md#update-a-product-and-update-its-media)
 * [Create a category and assign products to it](./assets/catalog-import/examples.md#create-a-category-and-assign-products-to-it)
 * [Create a product with a custom entity](./assets/catalog-import/examples.md#create-a-product-with-a-custom-entity)
 * [Update a custom entity](./assets/catalog-import/examples.md#update-a-custom-entity)

This endpoint is used to create and update entities. The data is stored for later import, specifically during the `commit` http call.

Note: They will not be imported in to the system yet.

This stage takes care of resolving specific associations and existence checks.

It is important to note that while it is possible to create nested entities, it is not possible to update them via the parent entity. Updates should be performed on the root level.

For example: you can create a product with nested media. If you want to update the media, you must do it at the root level, by referencing the entity directly (by its ID or any resolvers the entity supports).

This stage will also extract some inline records, for example, media records specified directly on the product and create separate records for them.
After that process, we will sort records based on their dependencies, to optimize for bulk inserts. This is only performed for entities which should be created.

Custom entities can also be added and updated in the same manner as normal entities, however, they must be defined under the `extensions` key due to the fact that the schema is not known ahead of time. See above examples relating to custom entities.

For example, consider the following record set:

```
├── Product 1
│   ├── Tax 1
│   ├── Media 1
│   ├── Media 2
├── Product 2
│   ├── Tax 2
│   ├── Media 3
```

The entities will be assigned a level value like so:

| Entity    | Level |
|-----------|-------|
| Tax 1     | 1     |
| Tax 2     | 1     |
| Media 1   | 1     |
| Media 2   | 1     |
| Media 3   | 1     |
| Product 1 | 0     |
| Product 2 | 0     |

With this data, we can decide which records can be processed at the same time. The highest level has no dependencies. In this case, level 1. All of those records can be processed at the same time. Once done, the next level can be processed.

The records will be saved into a simple intermediate store, in a generic structure, not related to the database or DAL.

The store will either be something like redis or mysql. Regardless, this store will be behind an interface and will be exchangeable.

You can push as many records to this end point as you wish. When you are done, you call the commit endpoint.

Calling 'record' is not possible after an import has been committed. An error will be returned.

### Deleting entities 

`POST /api/import/{{import_id}}/record/delete`:

#### Examples

* [Delete a product & media](./assets/catalog-import/examples.md#delete-a-product-and-media)

This endpoint is used to delete entities from the system, as with POST calls to the `/record` endpoint, the deletions are only queued. They delete will happen during the `/commit` call.

The payload is a simple array of entities with an array of ID's to delete.

### Unassigning nested entities 

`POST /api/import/{{import_id}}/record/unassign`:

#### Examples

* [Un-assign a media from a product](./assets/catalog-import/examples.md#un-assign-a-media-from-a-product)

This endpoint is used to remove entity associations. 

### Committing an import 

`POST /api/import/{{import_id}}/commit`

This endpoint is used to commit the records and begin the actual import into Shopware.

As previously noted, records will be batched by workers according to their level, traversing from the highest level to the lowest. During this process, the generic record structure will be mapped to the expected DAL structure.

Internally, the sync API will be used to perform the actual "inserts".

Calling commit can only be performed once. Any subsequent commits will return an error.

### Getting the status of an import 

`POST /api/import/{{import_id}}/status`

This endpoint returns the status of the import.

Subsequent calls to the status end point will return the current status of the import and errors reported so far.

The status will be reported as one of `started`, `importing`, `cancelled` or `done`. 

When the import is finished, `status` will be reported as `done`, and all the errors will be included and the duration and totals will reflect the total imported.

### Cancelling an import 

`POST /api/import/{{import_id}}/cancel`

This endpoint cancels a non-committed import. It's not possible to cancel an already committed import.

Cancelling an import will delete all records pushed to it. This might be useful in case some erroneous data was pushed to the import.


### Http Schema
[Openapi schema](./assets/catalog-import/import-http-schema.yml)

### Resolver Concept

When associating records, it will be possible to specify the ID of the association, for example, when you want to assign a tax entity to a product, you can pass the tax ID.

The problem here is that you may not know, or want to keep a record of the referenced entity ID.

We will introduce the concept of Resolvers to solve this problem.

The job of a resolver is to look for references to its entities (in this case Tax) and resolve any of them which are not ID references, to ID references.

In the initial experimental release, we will ship with a minimal set of reference resolvers to prove the concept. We will also explore how we can open it up for extension developers to provide reference resolvers and have them automatically integrated with the API specification.

## Media Uploading

In the first iteration, we will only support uploading media files from an external URL. The image will be downloaded during import.

We will still honor the configuration setting `media.enable_url_upload_feature`. If the config is disabled, the import will skip importing media.

## Error handling

### Whilst adding import records

Whilst adding import instructions via the '/record' endpoints, errors will be directly reported to you regarding resolving. For example, if you try to update an entity which does not exist. Or assign an entity to another that does not exist.

See [Error Response: Resolving Root Entities](./assets/catalog-import/examples.md#error-response-resolving-root-entities) for the request and response when trying to update a product which does not exist.

When a nested entity does not exist, the error is reported under the root entity. When an error occurs in a nested entity, the root entity will not be updated.

See [Error Response: Resolving Nested Entities](./assets/catalog-import/examples.md#error-response-resolving-nested-entities) for the request and response when trying to assign products which do not exist to a category.

### Whilst committing

* When an error occurs created a nested entity, the root entity will also fail, it will not be updated.
* When a root entity cannot be updated or created, all nested entities will be deleted. Note that it is not possible to update nested entities, so there is no issue with rolling back nested entities to their previous state.

See [Error Response Status Root](./assets/catalog-import/examples.md#error-response-status-root) for the request and response when trying to create a product with a not unique product number.
See [Error Response Status Nested](./assets/catalog-import/examples.md#error-response-status-nested) for the request and response when trying to create a product with a media which cannot be downloaded.


## Error reporting

Errors will be reported in real time to a given webhook URL, or via the `status` end point under the error key. The properties are described in the `Failure` object of the API specification.

Note: the webhook will not be implemented in the first draft and the general concept is open for discussion.

The errors will be keyed by the id of the entity given during the `record` endpoint.

Errors will be classified with a severity using the levels from [RFC5424](https://datatracker.ietf.org/doc/html/rfc5424).

# Appendix

## Appendix A: An example PHP payload to import a product with categories, media and tax records.

```php
$myProductData = [
        [
            'id' => '018a6b222b5a734d956fb03dda765bf8',
            'name' => 'Cool Prod 1',
            'productNumber' => 'COOLPROD1',
            'tax' => [
                'name' => 'Reduced rate 2', //The tax record is referenced via its name attribute instead of its ID
            ],
            'prices' => [
                [
                    'currency' => 'EUR',
                    'gross' => 15,
                    'net' => 10,
                    'linked' => false,
                ],
            ],
            'stock' => '100',
            'categories' => [
                [
                    'path' => ['Home', 'Category 2', 'Category 3'], //The product will be assigned to the category: Home/Category 2/Category 3
                ],
            ],
            'media' => [
                [
                    //this image will be downloaded and assigned to a media record, and then assigned to the product
                    'url' => 'https://images.unsplash.com/photo-1660236822651-4263beb35fa8?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80',
                    'title' => 'pommes',
                    'alt' => 'alt',
                    'filename' => 'other-media.jpg',
                ],
            ],
        ],
    ];
```
