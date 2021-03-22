---
title: Improve write performance
issue: NEXT-13939 
---
# Core
* Added `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater::batchUpdate` which scales better than `update`
* Removed method `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater::update`, use `batchUpdate` instead
* Changed `\Shopware\Core\Content\Category\DataAbstractionLayer\CategoryIndexer` to only call into `TreeUpdater` for the categories that have changed parents
* Added `\Shopware\Core\Framework\Validation\HappyPathValidator` which handles most of our constraint validation more efficiently and 
  calls into the normal validator as a fallback if it fails, or an unknown constraint is used
* Added some micro-optimizations to the DAL
* Added method `normalize` to the interface `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface`
* Added method `prefetchExistences` to the interface `\Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface`
* Added `\Shopware\Core\Framework\DataAbstractionLayer\Write\PrimaryKeyBag` which is used to collect primary keys in the normalize step and later used to batch the existences queries.
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway` to batch inserts and upserts for `MappingEntityDefinition`s
___
# Upgrade Information

## TreeUpdater scaling

We've replaced `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater::update` with `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater::batchUpdate`, 
because `update` scaled badly with the tree depth. The new method takes an array instead of a single id.

## EntityWriteGatewayInterface

We've added the new method `prefetchExistences` to the interface `\Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface`. 
The method is optional, and a valid implementation is to not prefetch anything. The method was added to allow fetching the existence of more than one primary key at once.

## FieldSerializerInterface::normalize

We've added the new method `normalize` to the interface `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface`.
A valid implementation is to just return `$data`. The `AbstractFieldSerializer` does that already. 
The method should normalize the `$data` if it makes sense. For example, the core serializers do the following in the normalize step:
- generate missing ids (`IdField`)
- resolve foreign keys (`FkField` and `Association`)
- normalize structure for example for translations (there are multiple ways to define them)
- collect primary keys in `PrimaryKeyBag`
