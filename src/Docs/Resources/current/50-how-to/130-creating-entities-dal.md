[titleEn]: <>(Creating entities via DAL)
[metaDescriptionEn]: <>(You want to create a new entry for an existing entity in your plugin, e.g. adding a new tax rate upon installing your plugin. The best and most recommended way for this is to use the Shopware 6 data abstraction layer.)
[hash]: <>(article:how_to_create_entity_dal)

## Overview

You want to create a new entry for an existing entity in your plugin, e.g. adding a new tax rate upon installing your plugin.
The best and **most recommended** way for this is to use Shopware 6 [data abstraction layer](./../60-references-internals/10-core/130-dal.md).

All of the following methods are to be executed on the entities' respective repository.

## Using create

The `create()` method is for creating new entities that do not exist yet.

- The first parameter `$data` is the payload to be written
- The second parameter `$context` is the context to be used when writing the data

The writing process works in batch and requires you to provide a list of data to be written.
Even if you want to create a single entity, it must be provided as an array containing a single item.

**Single entity**

```php
/** @var EntityRepositoryInterface $taxRepository */
$taxRepository = $this->container->get('tax.repository');

$taxRepository->create(
    [
        [ 'name' => '15% tax', 'taxRate' => 15 ],
    ],
    \Shopware\Core\Framework\Context::createDefaultContext()
);
```

**Multiple entities**

```php
/** @var EntityRepositoryInterface $taxRepository */
$taxRepository = $this->container->get('tax.repository');

$taxRepository->create(
    [
        [ 'name' => '15% tax', 'taxRate' => 15 ],
        [ 'name' => '25% tax', 'taxRate' => 25 ],
        [ 'name' => '35% tax', 'taxRate' => 35 ],
    ],
    \Shopware\Core\Framework\Context::createDefaultContext()
);
```

## Using upsert

The `upsert()` method is a great way for developers to ensure their data is reflected in the database,
no matter if they exists or not. It combines both `create()` and `update()` and is mainly used for syncing data.
If the described record exists, it will be updated, otherwise it will be created.
The method takes the same parameters as the `create()` or `update()` method.
Make sure to have a look at the explanation on [how to update entities via DAL](./150-updating-entities-dal.md).

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

$taxRepository->upsert(
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
 
 ## Working with relations
 
 A big advantage when using the DataAbstractionLayer is that you can provide an entire entity for the write.
 For example, you can create a product including all relations and even create them in place, without having to create the related records beforehand:
 
 ```php
/** @var EntityRepositoryInterface $productRepository */
$productRepository = $this->container->get('product.repository');

$productRepository->upsert(
    [
        [
            'name' => 'Example product',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false ]],
            'manufacturer' => [ 'name' => 'shopware AG' ],
            'tax' => [ 'name' => '19%', 'taxRate' => 19 ]
        ]
    ],
    Context::createDefaultContext()
);
 ```
 
 The example above will create a new product with an auto-generated identifier. In addition, it creates a new manufacturer named `shopware AG`
 and a new tax with a rate of `19%`.
 
 You don't have to care about writing orders or foreign key constraints if your definition and the database is designed correctly.
