[titleEn]: <>(Write)
[hash]: <>(article:dal_write)

# Write

As with all other operations in the system, it is important that writing operations
are as fast as possible. Furthermore, you do not want to worry about dependencies on
entities and that they are processed in the right order.

Therefore, all write operations are processed as batch operations so all associated data
can be processed in the same operation.

## Create, Update, Upsert

Unlike other ORMs, the system does not work with model or entity classes when writing data.
Instead, the system works with simple arrays, so that no read operation
must take place before-hand.

There are three methods in an entity repository for writing data: `create()`, `update()`
and `upsert()`.

### Create

The `create()` method is for creating new entities that do not exist yet.

- The first parameter `$data` is the payload to be written.
- The second parameter `$context` is the context to be used when writing the data.

As mentioned above, the writing process works in batch and requires you the provide a list of
data to be written. Even if you want to create a single entity, it must be provided as a list containing
a single item.

**Single entity**

```php
$repository->create(
    [
        ['name' => '15% tax', 'rate' => 15],
    ],
    $context
);
```

**Multiple entities**

```php
$repository->create(
    [
        ['name' => '15% tax', 'rate' => 15],
        ['name' => '19% tax', 'rate' => 19],
        ['name' => '35% tax', 'rate' => 35],
    ],
    $context
);
```

### Update

The `update()` method is for updating existing entities and takes the same parameters as
the `create()` method.

- The first parameter `$data` is the payload to be written.
- The second parameter `$context` is the context to be used when writing the data.

Keep in mind, that every top-level record needs an existing `id` property, otherwise, you'll get
exceptions because of the missing or non-existing records.

**Single entity**

```php
$repository->update(
    [
        ['id' => 'e163778197a24b61bd2ae72d006a6d3c', 'name' => 'updated name'],
    ],
    $context
);
```

**Multiple entities**

```php
$repository->update(
    [
        ['id' => 'e163778197a24b61bd2ae72d006a6d3c', 'name' => 'updated name'],
        ['id' => '11cf2cdd303c41d7bf66808bfe7769a5', 'name' => 'another name'],
        ['id' => 'a453634acb414768b2542ae9a57639b5', 'rate' => 100, 'name' => 'very expensive'],
    ],
    $context
);
```

### Upsert

The `upsert()` method is a great way for developers to ensure their data is reflected in the database,
no matter if they exists or not. It combines both `create()` and `update()` and is mainly used
for syncing data. If the described record exists, it will be updated otherwise it will be created.
The method takes the same parameters as the `create()` or `update()` method.

- The first parameter `$data` is the payload to be written.
- The second parameter `$context` is the context to be used when writing the data.

**Single entity**

```php
$repository->upsert(
    [
        ['name' => 'i will be created'],
    ],
    $context
);
```

**Multiple entities**

```php
$repository->upsert(
    [
        ['id' => 'e163778197a24b61bd2ae72d006a6d3c', 'name' => 'i will have an updated name'],
        ['name' => 'i am a new record'],
    ],
    $context
);
```

**To keep it simple:** If you provide an `id`, the system will try to update an existing record, if there is no
 record it, it will be created with the provided `id`.

## Working with relations

A big advantage when using the DataAbstractionLayer is that you can provide an entire entity for the write.

For example, you can create a product including all relations and even create them in place,
without having to create the related records beforehand:

```php
$repository->upsert(
    [
        [
            'name' => 'Example product',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'shopware AG'],
            'tax' => ['name' => '19%', 'rate' => 19]
        ]
    ],
    $context
);
```

The example above will create a new product with an auto-generated identifier. In addition, it
creates a new manufacturer named `shopware AG` and a new tax with a rate of `19%`.

You don't have to care of writing orders or foreign key constraints if your definition and
the database is designed correctly.

### Link existing entities

In most cases, you already have some entities created and just want to link them to a new entity or
link an existing entity to another.

You can either provide the property of the `FkField` in your definition like `taxId` or use the
nested syntax like when creating a new related entity but provide an existing ID.

**Using the FkField**

```php
$repository->upsert(
    [
        [
            'name' => 'Example product',
            'manufacturerId' => '5a4aa22452e04acca23185d4d21bb3bf',
        ]
    ],
    $context
);
```

**Using the association**

```php
$repository->upsert(
    [
        [
            'name' => 'Example product',
            'manufacturer' => ['id' => '5a4aa22452e04acca23185d4d21bb3bf'],
        ]
    ],
    $context
);
```

Both ways are supported and will result in the same.

## Client-generated identifiers

The DataAbstractionLayer works with UUIDs as identifiers for entities. If you don't provide a UUID when creating
records, they will be auto-generated.

If you are writing complex applications and have some business logic which is handled on
client-side, you have the option to generate the UUID yourself and send them via the `id` property.
The only requirement is that it should be unique system-wide and compatible with UUIDv4.

To generate a UUID in the system, you can use `Shopware\Core\Framework\Uuid\Uuid` and create
a new UUID by invoking `Uuid::randomHex()`.
