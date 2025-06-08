---
title: Use sql null safe equals operator
issue: NEXT-13838
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter`, the filter is now interpreted with the mysql NULL-safe equal operator.
___
# Upgrade Information
## Not filter with equals filter
We have changed the interpretation of the `EqualsFilter` to mysql's NULL-safe equal operator. The effects can best be explained with the following queries.

```php
$criteria = new Criteria();
$criteria->addFilter(
    new NotFilter(NotFilter::CONNECTION_AND, [
        new EqualsFilter('product.manufacturer.name', 'shopware')
    ])
);
```

Before the change, the above query returned all products that have a manufacturer whose name is not `shopware`. However, only products were returned which actually have a manufacturer.
This is logically not correct, because a product which does not have a manufacturer should also be present in the result.
After the change now also products are returned, which have no manufacturer assigned.
To restore the old behavior, a NOT-NULL check on the manufacturer id can be added:

Translated with www.DeepL.com/Translator (free version)
```php
$criteria = new Criteria();
$criteria->addFilter(
    new NotFilter(NotFilter::CONNECTION_AND, [
        new EqualsFilter('product.manufacturer.id', null),
        new EqualsFilter('product.manufacturer.name', 'shopware')
    ])
);
```
