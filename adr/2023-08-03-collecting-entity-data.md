---
title: Collecting and dispatching entity data
date: 2023-08-03
area: data-services
tags: ['entity', 'usage-data', 'ai', 'machine-learning']
---

## Context
*shopware AG* aims to provide data-driven features to its merchants.
The foundation for these features are data that our merchants provide to us with their consent.
A subset and primary pillar of these data lies within the entities, stored within each and every Shopware shop.
This ADR addresses main concepts of extracting the information out of the shops and transferring it to *shopware AG*.

## Decision
### No data sharing without consent
Merchants must explicitly agree and consent to share their data with *shopware AG*.
As long as there is no consent, no data will be collected or transferred.
The consent to data sharing can be revoked at any time by the merchants.

We will actively prompt Administration users to decide whether they are willing to give their consent to data sharing on the Administration's dashboard.
Changing the consent state later is possible via the system settings. To keep track of the consent changes, we will send to and store it on our gateway.

### No collection of sensitive information
Data stored in all types of entities might contain sensitive information including e.g. personal or business critical information.
This kind of data is excluded.

### Personally identifiable information (PII)
Data that would enable *shopware AG* to identify a person is modified in such a way that it is no longer possible to draw conclusions about the person.
A so-called *personal unique identifier (PUID)* is generated to identify users across multiple sources (e.g. entity data, on-site tracking) with the goal of analyzing their behavior which is used for generating insights and making predictions.
Again, it is not possible to find out **who** the person is, just that it is the **same** person.

### Transitioning to data pulling
The processes described in this ADR can be viewed as an approach of *data pushing*.
Data is fetched from the database and prepared on the merchant's servers and infrastructure before it is sent to *shopware AG*.

To be more flexible and to reduce the load on our merchant's infrastructure, we plan to transition to a *data pulling* approach.
With this approach we are planning to use Shopware's Admin API to fetch the data, rather than fetching it from the database directly.

### Providing data-driven features via the app system
The features built upon the data that is collected, will be rolled out as an extension based on the app system.
This way, we make feature releases independent of the Shopware 6 release cycle and can provide new features faster.

### Including entities and fields
By default, entities are not considered for data collection.
Only entities and their fields listed in an allow-list will be included in the data collection.

The format looks as follows:
```json
{
    "entity_one": [
        "fieldOne",
        "fieldTwo",
        "fieldThree"
    ],
    "entity_two": [
        "fieldOne",
        "fieldTwo"
    ],
    "entity_three": [
        "fieldOne"
    ]
}
```

Example:
```json
{
    "category": [
        "id",
        "parentId",
        "type"
    ],
    "product": [
        "id",
        "parentId",
        "name"
    ]
}
```

#### Many-to-many associations
Entities representing many-to-many associations between other entities should not be included in the allow-list.
Instead, they are either fetched from the database directly or resolved by querying the associated entity table.

When adding a many-to-many association to the allow-list, the referenced field is the `associationName` instead of the `propertyName`.

Example:

```php
class ProductDefinition
{
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            // ...
            (new ManyToManyIdField('category_ids', 'categoryIds', associationName: 'categories'))->addFlags(new ApiAware(), new Inherited()),
            (new ManyToManyAssociationField('categories', CategoryDefinition::class, ProductCategoryDefinition::class, 'product_id', 'category_id'))->addFlags(new ApiAware(), new CascadeDelete(), new Inherited(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            // ...
            (new ManyToManyAssociationField('tags', TagDefinition::class, ProductTagDefinition::class, 'product_id', 'tag_id'))->addFlags(new CascadeDelete(), new Inherited(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING), new ApiAware()),
            // ...
        ]);
    }
}
```

```json
{
    "product": [
        "categories",
        "tags"
    ]
}
```

The allow-list contains the `categories` and the `tags` field of the product entity. When the data is queried from the database, the many-to-many associations are resolved as follows:
* Identifiers of many-to-many associations that have a corresponding `ManyToManyIdField` are fetched from the database directly.
* Other many-to-many associations are resolved by fetching the associated entities from the database beforehand and matching them against the currently processed entity.

#### Associations problem: product -> category

#### Translated fields
Translated fields are not resolved automatically.
Instead, translation entities must be added to the allow-list explicitly.

## Consequences
#### Activating and deactivating the data collecting process
The process will be triggered once a day by a scheduled task as long as the consent is given.
It will also be triggered right away when the consent is given.
The merchant can revoke the consent at any time, which will prevent the process from starting.

#### Collecting data asynchronously
Once the process is running, for each entity definition, some messages will be added to a low priority message queue, and so they will be processed asynchronously.
The process will create batches of up to 50 entities (configurable) before sending them to the gateway.

#### First run and consecutive runs
Deltas of the data are calculated after the first time the data is sent, so consecutive runs are lighter and faster.
In order to achieve this, the process will keep track of the last time the data was sent and will only send the data that was created or updated after that time.

For deletions, an event subscriber will take care of storing the deletions of the entities.
These deletions will be sent and deleted when the process is run.
No deletion will be stored if the consent for collecting data is revoked or not given in the first place.

#### Remote kill-switch
A kill-switch on the Gateway enables us to (temporarily) stop shops from sending us data.
Messages already dispatched to the queue will still be handled but no new messages will be added by the scheduled task if the kill-switch is enabled.
