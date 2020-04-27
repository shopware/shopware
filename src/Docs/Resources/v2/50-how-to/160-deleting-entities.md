[titleEn]: <>(Deleting entities via DAL)
[metaDescriptionEn]: <>(When you want to get rid of an database entry, you'd probably write a SQL 'DELETE' query to do the job. In Shopware 6 though it is highly recommended to use the data abstraction layer for such tasks.)
[hash]: <>(article:how_to_delete_entity_dal)

## Overview

When you want to get rid of an database entry, you'd probably write a SQL `DELETE` query to do the job.
In Shopware 6 though it is **highly recommended** to use the [data abstraction layer](./../60-references-internals/10-core/130-dal.md) for such tasks.

## Delete

An entities respective repository always comes with a `delete` method, whose usage is as simple as this:

- The first parameter `$data` is the payload of the entity to be deleted, must only contain the ID
- The second parameter `$context` is the context to be used when deleting the data

**Single entity**

```php
/** @var EntityRepositoryInterface $productRepository */
$productRepository = $this->container->get('product.repository');

$productRepository->delete(
    [
        [ 'id' => 'e163778197a24b61bd2ae72d006a6d3c' ],
    ],
    \Shopware\Core\Framework\Context::createDefaultContext()
);
```

**Multiple entities**

```php
/** @var EntityRepositoryInterface $productRepository */
$productRepository = $this->container->get('product.repository');

$productRepository->delete(
    [
        [ 'id' => 'e163778197a24b61bd2ae72d006a6d3c' ],
        [ 'id' => 'c3d6a600d27ea2db16b42a791877361e' ],
    ],
    \Shopware\Core\Framework\Context::createDefaultContext()
);
```

### Deleting entities without an ID

You might have noticed, that you're required to know an entities' ID in order to delete it.
You can find a short explanation on how to figure out an entities' ID in our [Reading entities via DAL](./140-reading-entities-dal.md) HowTo.
