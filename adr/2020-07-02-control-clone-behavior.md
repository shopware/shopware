# 2020-07-02 - Get control of association clone behavior as developer

## Context
The developer should be able to define, if an association has to be considered or skipped during the cloning of an entity.

## Decision
The current clone behavior is controlled by the `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete` flag.
All associations which are marked with this flag are considered in the clone process.
We will add an optional parameter to the flag constructor to disable this behavior.

I already added this flag to the following associations:
* `product.productReviews` 
    * This association is already overwritten by the administration
* `product.searchKeywords` 
    * Will be indexed by the product indexer, so we can skip this association in the clone process
* `product.categoriesRo` 
    * Will be indexed by the product indexer, so we can skip this association in the clone process

An example looks like the following:
```
(new OneToManyAssociationField('searchKeywords', ProductSearchKeywordDefinition::class, 'product_id'))
    ->addFlags(new CascadeDelete(false)),
```

## Consequences
After 6.3 released, the developer can control this behavior by setting `\Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete::$cloneRelevant` to false
