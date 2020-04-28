[titleEn]: <>(Reading entities via DAL)
[metaDescriptionEn]: <>(Very often one wants to read data from the database and therefore has to write his own queries using PDO. In Shopware 6, it's highly recommended to not write custom queries in order to fetch data, but to use the methods from our data abstraction layer, in short DAL.)
[hash]: <>(article:how_to_read_entity_dal)

## Overview

Very often one wants to read data from the database and therefore has to write his own queries using PDO.
In Shopware 6, it's **highly recommended** to not write custom queries in order to fetch data, but
to use the methods from our [data abstraction layer](./../60-references-internals/10-core/130-dal.md), in short `DAL`.

Here's a few examples on how to read your entity data using the DAL.
All of the following methods are to be executed on the entities' respective repository.

## Reading entities

The entity repositories provide a `search()` method which takes two arguments:

1. The `Criteria` object, which holds a list of ids.
2. The `Context` object to be read with

```php
/** @var EntityRepositoryInterface $productRepository */
$productRepository = $this->container->get('product.repository');


/** @var EntityCollection $entities */
$entities = $productRepository->search(
    new Criteria([
        'f8d36562c5614c5994aecb9c73d2b13e',
        '67a8a047b638493d95bb2a4cdf351cf3',
        'b94055962e4b49ceb86f55f8d1932607',
    ]),
    \Shopware\Core\Framework\Context::createDefaultContext()
);
```

The return value will be a collection containing all found entities as hydrated objects.

### Reading entities without an ID

In many cases you don't even know the ID of the entity you're looking for.
In order to search for entities using something else than the ID, you'll have to use filters.
[Read here](./../60-references-internals/10-core/130-dal.md) for more information about the DAL filter types and how to use them.

The following example code will be looking for a product whose `name` equals 'Example product':

```php
 /** @var EntityRepositoryInterface $productRepository */
$productRepository = $this->container->get('product.repository');


/** @var EntityCollection $entities */
$entities = $productRepository->search(
    (new Criteria())->addFilter(new EqualsFilter('name', 'Example product')),
    \Shopware\Core\Framework\Context::createDefaultContext()
);
```
