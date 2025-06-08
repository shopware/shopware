---
title: Implement dal join filter
issue: NEXT-12156
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Added `src/Core/Content/Test/Product/ProductBuilder.php`, which allows to build example products in unit tests easily
* Added `src/Core/Framework/DataAbstractionLayer/Dbal/FieldResolver/AbstractFieldResolver.php` which replaces the `src/Core/Framework/DataAbstractionLayer/Dbal/FieldResolver/FieldResolverInterface.php`
* Deprecated `src/Core/Framework/DataAbstractionLayer/Dbal/JoinBuilder/ManyToManyJoinBuilder.php`, logic was moved into the `src/Core/Framework/DataAbstractionLayer/Dbal/FieldResolver/ManyToManyAssociationFieldResolver.php`
* Deprecated `src/Core/Framework/DataAbstractionLayer/Dbal/JoinBuilder/ManyToOneJoinBuilder.php`, logic was moved into the `src/Core/Framework/DataAbstractionLayer/Dbal/FieldResolver/ManyToOneAssociationFieldResolver.php`
* Deprecated `src/Core/Framework/DataAbstractionLayer/Dbal/JoinBuilder/OneToManyJoinBuilder.php`, logic was moved into the `src/Core/Framework/DataAbstractionLayer/Dbal/FieldResolver/OneToManyAssociationFieldResolver.php`
* Deprecated `src/Core/Framework/DataAbstractionLayer/Dbal/JoinBuilder/TranslatedJoinBuilder.php`, logic was moved into the `src/Core/Framework/DataAbstractionLayer/Dbal/FieldResolver/TranslationFieldResolver.php`
* Added `src/Core/Framework/DataAbstractionLayer/Dbal/FieldResolver/FieldResolverContext.php` which contains all parameters for the `AbstractFieldResolver`
* Added `src/Core/Framework/DataAbstractionLayer/Dbal/CriteriaQueryBuilder.php` which replaces the `src/Core/Framework/DataAbstractionLayer/Dbal/CriteriaQueryHelper.php`
* Deprecated `src/Core/Framework/DataAbstractionLayer/Dbal/CriteriaQueryHelper.php` 
* Deprecated `src/Core/Framework/DataAbstractionLayer/Dbal/FieldResolver/FieldResolverInterface.php`
* Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::resolveAntiJoinAccessors`
* Added `src/Core/Framework/DataAbstractionLayer/Dbal/JoinGroup.php` which is used internally to detect how often a to many association has to be joined
* Added `src/Core/Framework/DataAbstractionLayer/Dbal/JoinGroupBuilder.php` which detects the `DataAbstractionLayer/Dbal/JoinGroup`
* Deprecated `src/Core/Framework/DataAbstractionLayer/Search/Filter/AntiJoinFilter.php`
* Added `src/Core/Framework/DataAbstractionLayer/Search/Filter/SingleFieldFilter.php` which is used for all filters which filters a single fields
___
# Upgrade Information
## Join Filter
With the new join filter logic, some queries of the DAL may return a different result. Each filter which is added to the criteria directly and contains a reference to a
to-many association, will lead to a sub-join with the corresponding filters inside.

If you add filters to a criteria which points to an to-many association field

So the following filters give two different results:

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
