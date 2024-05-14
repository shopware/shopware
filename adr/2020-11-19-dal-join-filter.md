---
title: DAL join filter
date: 2020-11-19
area: core
tags: [dal, join-filter, negated-filter, criteria]
---

## Context
Currently, there are various difficulties with the current implementation of the `anti-join-filter`. 
Sometimes this does not lead to the correct results or the query cannot be executed due to a PHP exception.
Furthermore the counterpart to the `anti-join-filter`, the `join-filter`, is missing. 
Currently the `anti-join-filter` is automatically assembled in the entity searcher if a `not-filter` exists that points to a field of an association.

### Anti join filter concept
The `anti-join-filter` should make sure that a `to-many` association can be queried negated on multiple values, here is an example:

**Give me all products which do not have "red" or "yellow" as property, but also not "XL" or "L".**
On the SQL side, the following query must be generated for this purpose:

```sql
SELECT product.id

FROM product
    LEFT JOIN property_properties color_filter
        ON color_filter.product_id = product.id
        AND color_filter.id IN ("red", "yellow")

    LEFT JOIN property_properties size_filter
        ON size_filter.product_id = product.id
        AND size_filter.id IN ("XL", "L")

WHERE size_filter.product_id IS NULL
AND color_filter.product_id IS NULL
``` 

### Join filter concept
The `join-filter` should make sure that a `to-many` association can be queried on multiple values, here is an example:

**Give me all products which do have "red" or "yellow" as property, but also "XL" or "L".**
On the SQL side, the following query must be generated for this purpose:

```sql
SELECT product.id

FROM product
    LEFT JOIN property_properties color_filter
        ON color_filter.product_id = product.id
        AND color_filter.id IN ("red", "yellow")

    LEFT JOIN property_properties size_filter
        ON size_filter.product_id = product.id
        AND size_filter.id IN ("XL", "L")

WHERE size_filter.product_id IS NOT NULL
AND color_filter.product_id IS NOT NULL
``` 

## Decision

Whether several joins must be made on an association must be recognized by the DBAL implementation itself. The user of the DAL does not have to pass an extra parameter for this.
However, since it is difficult to interpret what exactly is to be determined by the criteria, the algorithm for determination is based on certain rules.

We will form so called `join-groups` in the DAL. These are created per `multi-filter` layer. So a join to an association is only possible once per `multi-filter` layer. So we allow to query several fields within one join. 
But if an already filtered field is filtered in another or nested `multi-filter`, a separate join is created for this field.
It is only necessary to resolve the `to-many` association several times. 
After the `join-groups` have been formed, the field to be resolved is passed to the `FieldResolver` (which forms the SQL JOIN) and the filter in which this field is located.
Resolved filters in the JOIN are then marked and later in the WHERE they are linked with the corresponding AND/OR/NOT logic.

## Consequences
Queries against the DAL can now behave differently if multiple filters are set on a to many association.
To filter a to many association on multiple fields, where all filters should be related to each other, they must now be wrapped in a multi filter.

Note: If you use filters that filter on a to-many association field, you should check if the results of this query are still correct. It may be that more or less records are returned.

The following example shows how the filter behavior has changed on to-many associations:
```
1: 
$criteria->addFilter(
    new AndFilter([
        new EqualsFilter('product.categories.name', 'test-category'),
        new EqualsFilter('product.categories.active', true)
    ])
);

2:
$criteria->addFilter(
    new EqualsFilter('product.categories.name', 'test-category')
);
$criteria->addFilter(
    new EqualsFilter('product.categories.active', true)
);

```

1: Returns all products assigned to the `test-category` category where `test-category` is also active.
2: Returns all products that are assigned to the `test-category` category AND have a category assigned that is active.
