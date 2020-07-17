[titleEn]: <>(Updating entities via DAL)
[metaDescriptionEn]: <>(After creating entity, business processes often require you to update the previously created entity automatically. This can and should be done using the Shopware 6 data abstraction layer.)
[hash]: <>(article:how_to_update_entity_dal)

## Overview

After creating entity, business processes often require you to update the previously created entity automatically.
This can and **should** be done using Shopware 6 [data abstraction layer](./../2-internals/1-core/20-data-abstraction-layer/__categoryInfo.md).

Here are two methods on how to update your previously created entities.
Both of the following methods are to be executed on the entities' respective repository.

## Updating entities

The `update()` method is for updating existing entities and takes the same parameters as the `create()` method.
Read [here](./130-creating-entities-dal.md) to find out how to create entities in the first place.

- The first parameter `$data` is the payload to be written
- The second parameter `$context` is the context to be used when writing the data

Keep in mind, that every top-level record needs an existing `id` property, otherwise, you'll get exceptions because of the missing or non-existing records.

**Single entity**

```php
/** @var EntityRepositoryInterface $productRepository */
$productRepository = $this->container->get('product.repository');

$productRepository->update(
    [
        [ 'id' => 'e163778197a24b61bd2ae72d006a6d3c', 'name' => 'Updated name' ],
    ],
    \Shopware\Core\Framework\Context::createDefaultContext()
);
```

**Multiple entities**

```php
/** @var EntityRepositoryInterface $productRepository */
$productRepository = $this->container->get('product.repository');

$productRepository->update(
    [
        [ 'id' => 'e163778197a24b61bd2ae72d006a6d3c', 'name' => 'Updated name' ],
        [ 'id' => '11cf2cdd303c41d7bf66808bfe7769a5', 'name' => 'Another updated name' ],
        [ 'id' => 'a453634acb414768b2542ae9a57639b5', 'active' => 0, 'name' => 'Inactive product' ],
    ],
    \Shopware\Core\Framework\Context::createDefaultContext()
);
```

### Updating entities without an ID

You might have noticed, that you're required to know an entities' ID in order to update it.
You can find a short explanation on how to figure out an entities' ID in our [Reading entities via DAL](./140-reading-entities-dal.md) HowTo.

## Using Upsert

The `upsert()` method is a great way for developers to ensure their data is reflected in the database,
no matter if they exists or not. It combines both `create()` and `update()` and is mainly used for syncing data.
If the described record exists, it will be updated, otherwise it will be created.
The method takes the same parameters as the `create()` or `update()` method.
Make sure to have a look at the explanation on [how to create entities via DAL](./130-creating-entities-dal.md).

- The first parameter `$data` is the payload to be written
- The second parameter `$context` is the context to be used when writing the data

**Single entity**

```php
/** @var EntityRepositoryInterface $taxRepository */
$taxRepository = $this->container->get('tax.repository');

$taxRepository->upsert(
    [
        [ 'name' => 'I will be created' ],
    ],
    \Shopware\Core\Framework\Context::createDefaultContext()
);
```

**Multiple entities**

```php
/** @var EntityRepositoryInterface $taxRepository */
$taxRepository = $this->container->get('tax.repository');

$repository->upsert(
    [
        [ 'id' => 'e163778197a24b61bd2ae72d006a6d3c', 'name' => 'I will have an updated name' ],
        [ 'name' => 'I am a new record' ],
    ],
    \Shopware\Core\Framework\Context::createDefaultContext()
);
```

**To keep it simple:** If you provide an `id`, the system will try to update an existing record and if there is no
 record, it will be created with the provided `id`.
 If you don't provide the `id`, a new record will always be created.
 
 Note, that the container instance, `$this->container` in these cases, is not available in every case.
 Make sure to use the [DI container](https://symfony.com/doc/current/service_container.html) to inject the respective repository
 into your service, if the container instance itself is not available in your code.
