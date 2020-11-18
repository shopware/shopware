[titleEn]: <>(Database access)
[hash]: <>(article:developer_database)

## Database guide

In contrast to most Symfony applications, Shopware uses no ORM but a thin
abstraction layer called the data abstraction layer (DAL). The
[DAL](./../60-references-internals/10-core/130-dal.md)
is implemented with the specific needs of Shopware in mind and lets developers
access the database via pre-defined interfaces. Some concepts used by the DAL,
like Criteria, may sound familiar to you if you know
[Doctrine](https://symfony.com/doc/current/doctrine.html)
or other ORMs. For in-depth documentation about the DAL, visit the
[data abstraction layer](./../60-references-internals/10-core/130-dal.md)
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
// SwagExamplePlugin/src/Service/DalExampleService.php

public function __construct (EntityRepositoryInterface $productRepository)
{
    $this->productRepository = $productRepository;
}
```

If you're using [service autowiring](https://symfony.com/doc/current/service_container/autowiring.html), and the
type and argument variable names are correct, the repository will be injected automatically.

Alternatively, configure the `product.repository` service to be injected explicitly:

```xml
<!-- SwagExamplePlugin/src/Resources/config/service.xml -->

<service id="Swag\ExamplePlugin\Service\DalExampleService">
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
            'taxId' => '7ba4c94818e4414faca445f039288521',
            'stock' => 213,
            'productNumber' => 'swag-5642-0001',
            'price' => [
                [
                    'currencyId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    'gross' => 317.0,
                    'net' => 266.39,
                    'linked' => true,
                    'listPrice' => null,
                    'extensions'=>[]
                ]
            ]
        ],
        [
            'id' => '5fba5662f6f74547a0f75de3e0e6c8d2',
            'name' => 'Dolor sit amet',
            'taxId' => '7ba4c94818e4414faca445f039288521',
            'stock' => 49,
            'productNumber' => 'swag-5642-0002',
            'price' => [
               [
                   'currencyId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                   'gross' => 317.0,
                   'net' => 266.39,
                   'linked' => true,
                   'listPrice' => null,
                   'extensions'=>[]
               ]
            ]
        ]
    ],
    $context
);
```

Read more in the
[database abstraction layer](./../60-references-internals/10-core/130-dal.md)
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
[filters](./../60-references-internals/10-core/130-dal.md#filters)
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
[database abstraction layer](./../60-references-internals/10-core/130-dal.md)
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

## Enriching results with associations

Associations allow you to select more than the data of just one entity when
searching using the DAL. Assuming you've already built a `Criteria` object
for your search, an association can be added using the `addAssociation` method:

```php
$criteria->addAssociation('lineItems');
```

<!-- TODO: Link to reference documentation about associations (seemingly missing at the moment) -->

## Going further with extensions and custom entities

The DAL makes it possible to extend existing entities using new relations. This
can be useful when you need just a bit of additional data. Read more about
entity extensions
[here](./../50-how-to/180-entity-extension.md)
. Adding your own custom entity is possible as well and covered in-depth in the
[custom entity HowTo](./../50-how-to/050-custom-entity.md)
.
