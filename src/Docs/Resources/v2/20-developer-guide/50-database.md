[titleEn]: <>(Database access)
[hash]: <>(article:developer_database)

## Database guide

In contrast to most Symfony applications, Shopware uses no ORM but a thin
abstraction layer called the data abstraction layer (DAL). The
[DAL](./../../current/2-internals/1-core/20-data-abstraction-layer)
is implemented with the specific needs of Shopware in mind and lets developers
access the database via pre-defined interfaces. Some concepts used by the DAL,
like Criteria, may sound familiar to you if you know
[Doctrine](https://symfony.com/doc/current/doctrine.html)
or other ORMs. For in-depth documentation about the DAL, visit the
[data abstraction layer](./../../current/2-internals/1-core/20-data-abstraction-layer)
documentation in the internals section.

## CRUD

To give you a quick overview and a few workable examples, let's start with the
CRUD (create, read, update, delete) operations.

In each of the following examples an `EntityRepository` is used. This is the
recommended way for developers to interface with the DAL or the database in
general.

### Preconditions

Before using the repositories, you'll need to get them from the DIC. This is
done with
[constructor injection](https://symfony.com/doc/current/service_container/injection_types.html#constructor-injection)
, so you'll need to extend your services constructor by expecting an
`EntityRepositoryInterface`:

```php
// AcmeExamplePlugin/src/Service/DalExampleService.php

public function __construct (EntityRepositoryInterface $productRepository)
{
    $this->productRepository = $productRepository;
}
```

Then, configure the `product.repository` service to be injected:

```xml
<!-- AcmeExamplePlugin/src/Resources/config/service.xml -->

<service id="Acme\ExamplePlugin\Service\DalExampleService">
    <argument type="service" id="product.repository"/>
</service>
```

You can read more about dependency injection and service registration in
Shopware in the
[services HowTo](./../50-how-to/070-add-service.md)
.

### Creating entities

```php
$this->productRepository->create(
    [
        [
            'id' => '6dc5ca792e524cd2a4a719e9b804b410',
            'name' => 'Lorem ipsum',
        ],
        [
            'id' => '5fba5662f6f74547a0f75de3e0e6c8d2',
            'name' => 'Dolor sit amet',
        ]
    ],
    $context
);
```

Read more in the
[database abstraction layer](./../../current/2-internals/1-core/20-data-abstraction-layer/030-write.md)
documentation.

### Reading entities

To search for IDs, you may simply pass an array of IDs to the `Criteria`
constructor:

```php
$this->productRepository->search(
    new Criteria([
        '6dc5ca792e524cd2a4a719e9b804b410',
        '5fba5662f6f74547a0f75de3e0e6c8d2'
    ]),
    $context
);
```

To search an entity by other attributes, use a `Filter`:

```php
$this->productRepository->search(
    (new Criteria())->addFilter(new EqualsAnyFilter('name', [
        'Lorem ipsum',
        'Dolor sit amet'
    ])),
    $context
);
```

To find out more about filters, have a look at the
[filters](./../../current/2-internals/1-core/20-data-abstraction-layer/020-search.md#filter)
documentation.

### Updating entities

```php
$this->productRepository->update(
    [
        [
            'id' => '6dc5ca792e524cd2a4a719e9b804b410',
            'name' => 'Consectetur adipisci',
        ],
        [
            'id' => '5fba5662f6f74547a0f75de3e0e6c8d2',
            'name' => 'Elit',
        ]
    ],
    $context
);
```

Find out more in the
[database abstraction layer](./../../current/2-internals/1-core/20-data-abstraction-layer/030-write.md)
documentation.

### Deleting entities

```php
$this->productRepository->delete(
    [
        [
            'id' => '6dc5ca792e524cd2a4a719e9b804b410'
        ],
        [
            'id' => '5fba5662f6f74547a0f75de3e0e6c8d2'
        ]
    ],
    $context
);
```

## Enrich results with associations

Associations allow you to select more than the data of just one entity when
searching using the DAL. Assuming you've already built a `Criteria` object
for your search, an association can be added using the `addAssociation` method:

```php
$criteria->addAssociation('lineItems');
```

Learn more in the
[associations](./../../current/2-internals/1-core/20-data-abstraction-layer/020-search.md#associations)
documentation.

## Going further with extensions and custom entities

The DAL makes it possible to extend existing entities using new relations. This
can be useful when you need just a bit of additional data. Read more about
entity extensions
[here](./../../current/2-internals/1-core/20-data-abstraction-layer/060-extensions.md)
. Adding your own custom entity is possible as well and covered in-depth in the
[custom entity HowTo](./../50-how-to/050-custom-entity.md)
.
