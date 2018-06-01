# Table of content
- [Getting started with the core domain](#getting-started-with-the-core-domain)
- [Getting around the core structure](#getting-around-the-core-structure)
- [Talking to the API](#talking-to-the-api)
  * [Authentication](#authentication)
  * [Versioning](#versioning)
- [Working with data](#working-with-data)
  * [Searching data](#searching-data)
    + [Pagination](#pagination)
    + [Filtering](#filtering)
  * [Reading data](#reading-data)
    + [Keep the system fast - A short detour into performance optimization](#keep-the-system-fast---a-short-detour-into-performance-optimization)
    + [Api read](#api-read)
  * [Writing data](#writing-data)
- [Defining an entity](#defining-an-entity)
  * [Entity Definition class](#entity-definition-class)
    + [Add your first fields to an entity](#add-your-first-fields-to-an-entity)
    + [Define the primary key](#define-the-primary-key)
    + [Define a translatable Field](#define-a-translatable-field)
    + [Foreign Keys](#foreign-keys)
    + [Associations](#associations)
    + [Tenant](#tenant)
  * [Struct classes](#struct-classes)
  * [Collection classes](#collection-classes)
  * [Event classes](#event-classes)
- [Write your first plugin](#write-your-first-plugin)
  * [Plugin Bootstrap](#plugin-bootstrap)
  * [Include services.xml](#include-servicesxml)
  * [Entity Extension](#entity-extension)

# Getting started with the core domain
The core domain contains all sources related to the ORM. E.g. which data exist in the system, 
how the API can be addressed and all technical subtleties like file system abstraction, 
dependency injection or plugin system.
We use the Symfony 4 framework for the core. All data is stored in a configurable SQL environment 
and can be synchronized to storages like Redis or Elastic search to increase the performance.
The entire core is API Driven, meaning that all operations can be mapped through a corresponding API.
These include the normal CRUD operations of data as well as shopping cart calculations 
or even individual price calculation.
Furthermore, the core supplies its own ORM, which is has been specifically designed 
for the corresponding use cases in the Shopware universe.

# Getting around the core structure

The core domain is divided into different sub domains:
* Touchpoint
    * This part of the core contains all sources for building application access objects, 
      context objects or languages. Take a look into this domain in order to find out 
      which information is available in a user context, which information is stored 
      on an application or which translations are bound to the language system.
* Checkout
    * The checkout part includes all sources needed for the platform's checkout process. 
      These include, shopping cart calculation, customer administration, 
      ordering system and the payment/shipping system.
      Furthermore, here are the sources for the dynamic rule system of the platform. 
* Content
    * The content part contains sources that take care of the maintained contents of the platform.
      This includes product data, media, catalogs and categories. A look in this domain reveals which data can be served to different applications 
* Framework
    * In this part are the technical implementations of various components
      that are reused in the other domains. All sources, from ORM to the file system, 
      are included here.
* Profiling
    * The profiling part includes additional helper classes which simplify the profiling of 
    the platform. Among other things, the components implemented here expand the Symfony Toolbar.
* System
    * In the system part, entities are defined which are not specific to the various applications.
      They are reused by the several other domains. These include tax rates, configurations,
      currencies and locales.

# Talking to the API
As already described, the entire Core is API driven. When dealing with the API,
you should always consider two things: 

1. An authentication is required
2. There is an API versioning

All examples below are based on [Guzzle](http://docs.guzzlephp.org/en/stable/).

## Authentication
The first thing to think about is authentication. The core offers a bearer token authentication,
 which can be addressed via the route `/api/v1/auth`:
```php
<?php

$client = new \GuzzleHttp\Client();

$client->post(
    'http://shopware.local/api/v1/auth', 
    [
        'body' => json_encode([
            'username' => 'admin', 
            'password' => 'shopware'
        ])
    ]
);
```
The `/api/v1/auth` route can be used to authenticate with the system to allow further operations on data. The return will be a bearer token which has to be sent in the further request under the header `Authorization`:
```json
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VybmFtZSI6ImFkbWluIiwiaWF0IjoxNTI2NTU5ODkyLCJuYmYiOjE1MjY1NTk4OTIsImV4cCI6MTUyNjU2MzQ5Mn0.Xx3oeQoZmrRAq22PjKDYFou3e6AD8yWdVQc8qO7D9OA",
    "expiry": 1526563492
}
```
```php
<?php

$client = new \GuzzleHttp\Client();
$client->post(
    'http://shopware.local/api/v1/another-request',
    [
        'headers' => [
            'Authorization' => 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VybmFtZSI6ImFkbWluIiwiaWF0IjoxNTI2NTU5ODkyLCJuYmYiOjE1MjY1NTk4OTIsImV4cCI6MTUyNjU2MzQ5Mn0.Xx3oeQoZmrRAq22PjKDYFou3e6AD8yWdVQc8qO7D9OA',
        ]
    ]
);
```

## Versioning
As already seen above, the Core API offers a versioning. This allows us to implement 
breaking changes and to make them available even though there are systems that still work 
with old data formats or routes. One version number of the API will always support two 
major versions of the Platform. The API version v1 is therefore available simultaneously 
with the version v2 and will be switched off with the release of the v3. You will find out more about the API versioning later. 

# Working with data
The high-performance read and the fast (nested) writing of data is the foundation 
of all functions in the e-commerce. With the Core, we want to keep it as simple as possible, 
but as fast and flexible as possible for developers. Therefore, the core provides its own ORM,
which is developed to meet exactly our requirements. 

## Searching data
Searching data is a big deal in ORMs and e-commerce systems. If an API or a frontend does not 
offer proper search options, this is usually the downfall of the system. The ORM supplied by the Core
therefore has a strong focus on the ability to search data. All search queries about an entity are
governed by its associated repository class.

The repository classes are defined in the corresponding core parts, here are some examples which are available:
* `\Shopware\Content\Product\ProductRepository`
* `\Shopware\System\Touchpoint\TouchpointRepository`
* `\Shopware\System\Locale\LocaleRepository`
* `\Shopware\Checkout\Customer\CustomerRepository`

### Pagination
Lets start with a simple paginated list of products:

**Stack example:**

In the PHP stack we can get access to the repository via di container.
Each repository uses its class name as di container id. 
To search for entities, a `\Shopware\Framework\ORM\Search\Criteria` object is always used
```php
$repository = $this->container->get(ProductRepository::class);

$criteria = new Criteria();
$criteria->setOffset(0);
$criteria->setLimit(10);

$result = $repository->search($criteria, $context);
```

**API example request:** 

Each entity can also be accessed via API. Simple search queries can also be used via the 
corresponding entity routes: 
* `GET /api/v1/product?offset=0&limit=10` 

Complex queries need to be sent via the `/search` endpoint:
* `POST /api/v1/search/product`

The `/search` endpoint allows to provide 
the whole search `\Shopware\Framework\ORM\Search\Criteria` class via post:
```php
<?php

$client = new \GuzzleHttp\Client();

$client->post(
    'http://shopware.local/api/v1/search/product',
    [
        'body' => json_encode([
            'offset' => 0,
            'limit' => 10
        ]),
        'headers' => [
            'Authorization' => $token,
            'Content-type' => 'application/json',
            'ACCEPT' => ['application/vnd.api+json,application/json']
        ]
    ]
);
```

### Filtering
The platform provides a wide range of filter options. It is possible to filter all 
properties of an entity and its associations. As long as a link exists between two entities, 
they can also be filtered.

The range of filter options includes the following classes:
* `\Shopware\Framework\ORM\Search\Query\MatchQuery`
    * Allows to perform a string comparison (SQL: `LIKE`)
* `\Shopware\Framework\ORM\Search\Query\RangeQuery`
    * Query of a range of values (SQL: `<=`, `>=`, `>`, `<` )
* `\Shopware\Framework\ORM\Search\Query\TermQuery`
    * Query to filter for an exact value
* `\Shopware\Framework\ORM\Search\Query\TermsQuery`
    * Query to filter a set of exact values (SQL: `IN`)

To combine these different filter options, we have implemented query containers,
which allow you to link queries with each other or to negate them:
* `\Shopware\Framework\ORM\Search\Query\NestedQuery`
    * Allows you to group multiple queries and associate them with an operator `AND` or `OR`
* `\Shopware\Framework\ORM\Search\Query\NotQuery`
    * Allows to negate queries

**Stack example:**

Lets start with a simple filtered list of products and filter products which are not active:
```php
$repository = $this->container->get(ProductRepository::class);

$criteria = new Criteria();
$criteria->setOffset(0);
$criteria->setLimit(10);
$criteria->addFilter(new TermQuery('product.active', true));

$result = $repository->search($criteria, $context);
```

Now lets get a little more fuzzy and filter only products which costs between € 100 and € 200:
```php
$criteria->addFilter(
    new RangeQuery('product.price', [
        RangeQuery::GTE => 100,
        RangeQuery::LTE => 200
    ])
);
``` 

Next, only products are displayed where the manufacturer property `link` is defined
```
$criteria->addFilter(
    new NotQuery(
        new TermQuery('product.manufacturer.link', null)
    )
);
```

Furthermore, only products that were created on a specific date should be displayed:
```
$criteria->addFilter(
    new TermsQuery('product.createdAt', ['2018-01-01', '2018-02-01', '2018-03-01', '2018-04-01'])
);
```

And last but not least, only products that have the letter `A` in their name should be displayed:
```
$criteria->addFilter(
    new MatchQuery('product.name', 'A')
);
```

**API example request:**

The same filter possibilities are also offered by the API. With a small difference: 
If you call the entity endpoint, only the equals operator is supported. 
Range queries or others are not possible here:
* `GET /api/v1/product?filter[product.active]=1&filter[product.manufacturer.name]=Shopware` 

For more complex filtering, use the `/search` endpoint as mentioned above. 
In the first example of filtering, filters were made for products that have the active flag. 
So a TermQuery must also be sent via the API:
```php
<?php

$client = new \GuzzleHttp\Client();

$client->post(
    'http://shopware.local/api/v1/search/product',
    [
        'body' => json_encode([
            'offset' => 0,
            'limit' => 10,
            'filter' => [
                ['type' => 'term', 'field' => 'product.active', 'value' => 1]
            ]
        ]),
        'headers' => [
            'Authorization' => $token,
            'Content-type' => 'application/json',
            'ACCEPT' => ['application/vnd.api+json,application/json']
        ]
    ]
);
```
In the second example, the product list was filtered to a price range of € 100 to € 200 
using a range query. With range query you have to pass the corresponding operators 
like `greater than equals`, `less than` as `parameters`:
```php
<?php

$client = new \GuzzleHttp\Client();

$result = $client->post(
    'http://shopware.local/api/v1/search/product',
    [
        'body' => json_encode([
            'offset' => 0,
            'limit' => 10,
            'filter' => [
                [
                    'type' => 'range',
                    'field' => 'product.price',
                    'parameters' => ['gte' => 100, 'lte' => 200]
                ]
            ]
        ]),
        'headers' => [
            'Authorization' => $token,
            'Content-type' => 'application/json',
            'ACCEPT' => ['application/vnd.api+json,application/json']
        ]
    ]
);
```
Subsequently, products were excluded whose manufacturer has no link defined. 
This was solved by means of a negation. To do this, a `NOT` query must be sent to the API, 
which contains the other queries and negates them:
```php
<?php

$client = new \GuzzleHttp\Client();

$result = $client->post(
    'http://shopware.local/api/v1/search/product',
    [
        'body' => json_encode([
            'offset' => 0,
            'limit' => 10,
            'filter' => [
                [
                    'type' => 'not',
                    'queries' => [
                        ['type' => 'term', 'field' => 'product.manufacturer.link', 'value' => null]
                    ]
                ],
            ]
        ]),
        'headers' => [
            'Authorization' => $token,
            'Content-type' => 'application/json',
            'ACCEPT' => ['application/vnd.api+json,application/json']
        ]
    ]
);
```

The next step was to limit the list to products created on a specific date. 
The `terms` query is used for this:
```php
<?php

$client = new \GuzzleHttp\Client();

$result = $client->post(
    'http://shopware.local/api/v1/search/product',
    [
        'body' => json_encode([
            'offset' => 0,
            'limit' => 10,
            'filter' => [
                [
                    'type' => 'terms',
                    'field' => 'product.createdAt',
                    'value' => ['2018-01-01', '2018-02-01', '2018-03-01', '2018-04-01']
                ]
            ]
        ]),
        'headers' => [
            'Authorization' => $token,
            'Content-type' => 'application/json',
            'ACCEPT' => ['application/vnd.api+json,application/json']
        ]
    ]
);
```

And finally, the list was filtered to products that have the letter `A` in their name.
This is done using `match` queries:
```php
<?php

$client = new \GuzzleHttp\Client();

$result = $client->post(
    'http://shopware.local/api/v1/search/product',
    [
        'body' => json_encode([
            'offset' => 0,
            'limit' => 10,
            'filter' => [
                [
                    'type' => 'match',
                    'field' => 'product.name',
                    'value' => 'A'
                ]
            ]
        ]),
        'headers' => [
            'Authorization' => $token,
            'Content-type' => 'application/json',
            'ACCEPT' => ['application/vnd.api+json,application/json']
        ]
    ]
);
```

## Reading data
As already mentioned, the high-performance reading of data is decisive in e-commerce and for the API.
The reading of the data can only be controlled via the corresponding repositories.
These offer two options for this:
* `readBasic(array $ids, Context $context)`
* `readDetail(array $ids, Context $context)`

### Keep the system fast - A short detour into performance optimization
Both read functions work with a list of ids that should persuade developers to always read more
than one data at a time instead of requesting them one at a time. 
Hereby we would like to make a short deviation in the area of performance,
which is extremely important when it comes to maintaining a high-performance system:
Suppose we have to select 15 products on a system. We just get the ids back from the repository. 
Now there are several ways to determine the data for these 15 products. 
The following script shows two ways:
```
$repository = $this->container->get(ProductRepository::class);
 
$criteria = new Criteria();
$criteria->setLimit(15);

$ids = $repository->searchIds(
    $criteria, 
    $context->getApplicationContext()
);

// batch variant
$basics = $repository->readBasic(
    $ids->getIds(), 
    $context->getApplicationContext()
);


// loop variant
foreach ($ids->getIds() as $id) {
    $basics = $repository->readBasic(
        [$id], 
        $context->getApplicationContext()
    );
}
```
In the above example, the *LOOP variant* for reading the 15 products is **three time slower** compared to the *batch variant*. 
Therefore, you should always keep in mind during development to load data collected,
since the *batch variant* scales a lot better than the *loop variant*. 
If you increase the limit to 50 in the example above, the loop is **six times slower** than the batch variant.

### API Read 
To read a specific list of products via the API, we recommend using the `/search` endpoint
and restricting it to the corresponding ids with a term query:
```php
<?php

$client = new \GuzzleHttp\Client();

$result = $client->post(
    'http://shopware.local/api/v1/search/product',
    [
        'body' => json_encode([
            'offset' => 0,
            'limit' => 10,
            'filter' => [
                [
                    'type' => 'terms',
                    'field' => 'product.id',
                    'value' => ['0027ace18888421ca8ceb52a354c6cea', '00caf57d337b451d949f2482712cb8ce', '00d1e4a0e3d846928da6120ecadb682a', '00e1e8229018483db86ead4e51928eca']
                ]
            ]
        ]),
        'headers' => [
            'Authorization' => $token,
            'Content-type' => 'application/json',
            'ACCEPT' => ['application/vnd.api+json,application/json']
        ]
    ]
);
``` 

## Writing data
As with all other operations in the system, it is important that writing operations
are as fast as possible. Furthermore, as a developer, you do not want to worry about 
dependencies on entities and that they are processed in the right order.
Therefore, all write operations are accepted by the ORM as a batch operation 
and all associated data can be processed in the same operation. The following three functions 
allow you to write data: 
* `update(array[] $data, Context $context): GenericWrittenEvent;`
    * Updates records. If a record does not exist, an exception is thrown
* `create(array[] $data, Context $context): GenericWrittenEvent;`
    * Creates records. If a record already exists, an exception is thrown
* `upsert(array[] $data, Context $context): GenericWrittenEvent;`
    * Creates or updates records. If the record does not exist, it will be created, 
      if it already exists, it will be updated.

**Repository example**
Unlike some other ORMs, the platform does not work with entity/model classes when writing data.
Instead the platform works with simple arrays. This has the advantage,
that no read operation must take place before:  
```
$repository = $this->container->get(ProductRepository::class);

$id = Uuid::uuid4()->getHex();

$repository->upsert(
    [
        [
            'id' => $id,
            'name' => 'Test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'rate' => 15]
        ]
    ],
    $context->getApplicationContext()
);
```
In the example above, a new product is created. At the same time, a new tax rate 
and a new manufacturer will be created because they were not sent with a corresponding id.

To link an existing manufacturer or tax rate, you can simply supply the corresponding foreign key:
```
$repository = $this->container->get(ProductRepository::class);

$id = Uuid::uuid4()->getHex();

$repository->upsert(
    [

        [
            'name' => 'Test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturerId' => '034BACA6D92641E89DB8DF9DDDC1B30D',
            'taxId' => '4926035368E34D9FA695E017D7A231B9'
        ]
    ],
    $context->getApplicationContext()
);
```

In order to link an existing manufacturer or tax rate and to update it at the same time, 
the corresponding foreign key must be sent along with the associated data array:
```
$repository = $this->container->get(ProductRepository::class);

$id = Uuid::uuid4()->getHex();

$repository->upsert(
    [
        [
            'id' => $id,
            'name' => 'Test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['id' => '034BACA6D92641E89DB8DF9DDDC1B30D', 'name' => 'test'],
            'tax' => ['id' => 4926035368E34D9FA695E017D7A231B9', 'name' => 'test', 'rate' => 15]
        ],
    ],
    $context->getApplicationContext()
);
```
 
**API example request**

The API also has these three ways to write entities into the system. 
These are reflected in the HTTP methods:
* POST  `/api/v1/product` - `create`
* PUT   `/api/v1/product/{id}` - `update`
* PATCH `/api/v1/product` - `upsert`

All three functions have the same syntax. For update operations it is not necessary to resend 
the entire entity. It is possible to send only the corresponding changeset.
```
$client = new \GuzzleHttp\Client();

$id = \Ramsey\Uuid\Uuid::uuid4()->getHex();

$result = $client->post(
    'http://shopware.local/api/v1/product',
    [
        'body' => json_encode([
            'id' => $id,
            'name' => 'Test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => '4926035368e34d9fa695e017d7a231b9', 'name' => 'test', 'rate' => 15]
        ]),
        'headers' => [
            'Authorization' => $token,
            'Content-type' => 'application/json',
            'ACCEPT' => ['application/vnd.api+json,application/json']
        ]
    ]
);
```

# Defining an Entity
An entity defines the data structure of a table in the system. But why do you need entities
and can not just work with arrays? The benefit of entities in any system is that you can rely on a
stricly defined structure. Every developer knows this structure and must stick to it. 
This is different for an array. If a repository defines that an array is returned
and that field name `XYZ` is included by default, it is not necessary for a developer
()who may be exchanging this repository) to provide the property `XYZ` too.
This would cause an problem since other developers rely on this property.

In most ORM the PHP properties are mapped to the table columns and provided with violations 
like "Can not be empty".
The core works a bit differently with entities than you might expect. First of all, there is no 
entity or model class. Also the column mappings do not happen in PHP annotations, YAML or Xml files, 
but an EntityDefinition class for each entity is required.
In this class, the corresponding columns and properties of an entity are defined as well as 
associated classes such as Events, Repositories, Structs, Collections, etc.
But why this overhead on classes and definition?!
The huge benefit here is, that the rest is automatically handled and registered by the system. 
Once an entity definition has been created with the associated classes, 
it can be selected by the ORM, the repositories can throw events for the loaded objects 
and the entity is automatically available via the API.

All classes for an entity are always in the corresponding domain folder. 
For products, the `Content/Product`. Each of these domains has the following structure:
* Aggregates - *Includes subordinate entities (e.g. `ProductTranslation`,` ProductCategory`)*
* Collection - *Includes all collection classes*
* Event - *Contains all events that are thrown to the entity*
* Exception - *Contains all exceptions that occur when working with the entity*
* Struct - *Contains the struct classes*
* Repository.php - *The corresponding repository class*
* Definition.php - *The corresponding definition class*

## Entity Definition class
In an entity definition class, the following information is recorded:
* Which fields does the entity consist of?
* Which events belong to the entity?
* Which repository belongs to the entity?
* Which DTO (Struct & Collection) classes belong to the entity?

Lets start with an empty entity definition:

```php
<?php declare(strict_types=1);

namespace Shopware\Content\Product;

use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\FieldCollection;

class ProductDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'product';
    }

    public static function getFields(): FieldCollection
    {
        return new FieldCollection([]);
    }
}
```
First, the `getEntityName` function defines which table this class refers to, 
in this example `product`.
Then in the `getFields` creates a `FieldCollection`, which facilitates working with entity fields.

### Add your first fields to an entity
Now we can fill the entity with fields. Let's start with simple fields:
 
```php
public static function getFields(): FieldCollection
{
    return new FieldCollection([
        new StringField('ean', 'ean'),
        new BoolField('active', 'active'),
        new IntField('stock', 'stock'),
        new FloatField('weight', 'weight'),
        new DateField('created_at', 'createdAt'),
    ]);
}
```
These are the available scalar data types which are supported by the ORM. 
Each of the fields gets passed two parameters: `Column name` &` Property name`. 
Thus, the entity now has the following properties to work with:
* `ean` 
* `active`
* `stock`
* `weight`
* `createdAt`

### Define the primary key
Now let's add a primary key for the entity that allows us to identify records of that entity 
to delete or update them:
```php
public static function getFields(): FieldCollection
{
    return new FieldCollection([
       (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),  
       // ...
    ]);
}
```
Here an `IdField` with the flags `PrimaryKey` and` Required` is added. 
Field flags allow certain states to be defined on fields, such as whether they are necessary
to write a record. The `PrimaryKey` flag used here defines that this field is part of the primary key.
The `Required` flag, on the other hand, defines that this value must be set during writing. 
As id, the platform uses UUIDs in the v4 standard. This has the advantage of being able to fully 
build entities together and cross reference them before they are written to the database.

### Define a translatable field
The previously created data has been defined as simple data types and does not offer the 
possibility to be translated into other languages of the system. 
Translatable data is always stored in a separate table. 

Why necessarily in a separate table? So that they are searchable ... and ... inheritable, 
but more on that later.

The corresponding translation table always has the suffix `_translation`, since in our example it 
is the entity` product`, the name of the translation table is `product_translation`. 
This table also has its own entity definition, but we will not go into that in this example. 
To declare a field as translatable, the ORM provides an dedicated class, the `TranslatedField`:
```php
public static function getFields(): FieldCollection
{
    return new FieldCollection([
       // ...
       new TranslatedField(new StringField('name', 'name'))  
       // ...
    ]);
}
```
However, the `TranslatedField` is only a container for all other fields, for example a `FloatField` 
can be inserted here if the value should also be translatable.

### Foreign keys
Next we want to link the product entity with a manufacturer. This means a foreign key must be
added to the product in which a manufacturer id is stored.

But what is a foreign key? A foreign key is used to define that the value of this field refers
to a record in another table. This allows us to create links between tables/entities.

However, we also want to make sure that only existing manufacturer ids can be stored,
as otherwise we would quickly produce inconsistent data in the system. 
So the ORM should do a cross-check. For this we can use a field of class `FkField`.
```php
public static function getFields(): FieldCollection
{
    return new FieldCollection([
       // ...
       new FkField('product_manufacturer_id', 'manufacturerId', ProductManufacturerDefinition::class),  
       // ...
    ]);
}
```
As a third parameter, the `FkField` gets the corresponding reference to the `EntityDefinition`.
As a result, the system recognizes that the stored foreign key is a manufacturer record 
and checks it accordingly when writing the record.

### Associations
Although a foreign key is already defined for the link to the manufacturer,
we can not yet read out the corresponding manufacturer for the product. 
For this the ORM needs an `*AssociationField`. The following associations can be defined:
* `OneToManyAssociationField`
* `ManyToManyAssociationField`
* `ManyToOneassociationField`

In case of the product-manufacturer relationship, we need a `ManyToOneAssociationFiel` here.
An association has several parameters that are commented inline in the following example: 
```php
public static function getFields(): FieldCollection
{
    return new FieldCollection([
       // ...
       new ManyToOneAssociationField(
            //name of the property
            'manufacturer',
            
            //storage name of the foreign key                         
            'product_manufacturer_id', 
            
            //reference definition class
            ProductManufacturerDefinition::class,
            
            //should be loaded in basic struct? 
            true,
            
            //reference storage name
            'id'
        ),
       // ...
    ]);
}
```

By defining this association, we can now work with the product and the manufacturer at the same time.
We can always address this association via the property name:
* To filter in the search - `new TermQuery('product.manufacturer.name', 'shopware')`
* To create a manufacturer directly when writing the product - `'manufacturer' => ['name' => 'shopware']`
* To determine the number of manufacturers of a product list - `new CountAggregation('product.manufacturer.id', 'manufacturer_count')` 
* To read the manufacturer of a product via API - `GET /api/v1/product/{id}/manufacturer`
* ...

### Tenant
The ORM offers a tenant support, so that several instances can work with the same database,

**What does this mean from a technical point of view?**
*Each entity has another column in the primary key named `tenant_id` and each foreign key must
also be defined with another `*_tenant_id `*

If we apply this to the example above, the following columns must be created in the `product` table:
* `tenant_id binary(16)` 
* `product_manufacturer_tenant_id binary(16)`

Accordingly, these fields must also be included in the `ProductDefinition`. 
For this the ORM brings its own field with itself:
 ```php
public static function getFields(): FieldCollection
{
    return new FieldCollection([
       // ...
       new TenantIdField(),
       // ...
    ]);
}
```
This field is automatically handled by the ORM and therefore does not have to be specified 
in any write operation. For the `product_manufacturer_tenant_id`, however, the definition class 
does not need its own field. This is automatically controlled in the `FkField`.

## Struct classes
To transport the data between the database and the corresponding endpoints, the core uses 
so-called struct classes. These are simple PHP classes in which the properties of an entity are 
defined as PHP properties and are available via getter/setter functions.
The ORM distinguishes here for the corresponding read functions in `*BasicStruct` and 
`*DetailStruct` classes.
In the `BasicStruct` class  are all the properties of an entity defined but not the relations. 
So you have a minimum set of properties in order to work with the entity. 
A simple struct class can look like this:
```
<?php declare(strict_types=1);

namespace Shopware\System\Locale\Struct;

use Shopware\Framework\ORM\Entity;

class LocaleBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $code;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $territory;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTerritory(): string
    {
        return $this->territory;
    }

    public function setTerritory(string $territory): void
    {
        $this->territory = $territory;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
```

The `DetailStruct` class also contains relations. Please keep in mind that it can have an significant
performance impact if you use the `DetailStruct`. So if possible, use the `BasicStruct`

## Collection classes
To simplify working with many records, the repository classes of the core do not return 
simple arrays with struct classes but a type aware collection class which contains the elements.
These classes can be iterated to easily handle all records:
```php
<?php

$repository = $this->container->get(ProductRepository::class);

$basics = $repository->readBasic($ids, $context->getApplicationContext());

foreach ($basics as $productBasicStruct) {
    echo $productBasicStruct->getName();
}
```
In addition, these collections provide small helper functions to make it easier to access 
collection's aggregated data:
```php
<?php

$repository = $this->container->get(ProductRepository::class);

$basics = $repository->readBasic($ids, $context->getApplicationContext());

$taxes = $basics->getTaxes();

$ids = $basics->getIds();

$prices = $basics->getPrices();
```

## Event classes
As event system, the core uses the 
[Symfony Event System](https://symfony.com/doc/current/components/event_dispatcher.html). 
Additionally, ORM entity-related events are thrown, including:
* If the entity has been loaded in basic form (`*BasicLoadedEvent`)
* If the entity has been loaded in detail form (`*DetailLoadedEvent`)
* If an entity has been loaded by ids (`*IdSearchResultLoadedEvent`)
* If a search was performed for the entity (`*SearchResultLoadedEvent`)
* If aggregations were found for an entity (`*AggregationResultLoadedEvent`)
* If the entity has been deleted (`*DeletedEvent`)
* If the entity has been written (`*WrittenEvent`)

# Write your first plugin
To be able to introduce extensions into the system, the core comes with an integrated plugin system.
Plugins are [Symfony Bundles](https://symfony.com/doc/current/bundles.html) which can be activated
and deactivated via the database or via the bin/console plugin:* command.
Now the question arises what is possible with plugins. The answer is simple: **Everything**, 
since a plugin can completely hook into the kernel's boot process.
**Everything** includes:
* Bring your own events and / or listen to existing events
* Include own entities in the system and / or extend existing entities
* Define own services and / or extend existing services or even exchange them completely with 
something even better

## Plugin Bootstrap
As an entry point into the system each plugin must have a bootstrap file. 
The corresponding plugin sources can be stored under `/custom/plugins`. 
As convention, there is a plugin folder for each plugin in the plugins folder with the 
corresponding name of the plugin. This folder contains the bootstrap file which must have the 
same name as the folder.
The following example shows the basic structure of a plugin which should be defined with the name 
"GettingStarted".
```php
<?php

//sources of custom/plugins/GettingStarted/GettingStarted.php

namespace GettingStarted;

use Shopware\Framework\Plugin\Plugin;

class GettingStarted extends Plugin
{
}
```
With these few lines of source code, the plugin can already be registered and installed in the 
system. Subsequently, further functions can be integrated into the bootstrap file in order 
to be able to react to certain actions in the system.
For example the actions when the plugin is installed, uninstalled, activated, deactivated or updated:
```
public function install(InstallContext $context)
{
    parent::install($context);
}

public function update(UpdateContext $context)
{
    parent::update($context);
}

public function activate(ActivateContext $context)
{
    parent::activate($context);
}

public function deactivate(DeactivateContext $context)
{
    parent::deactivate($context);
}

public function uninstall(UninstallContext $context)
{
    parent::uninstall($context);
}
``` 

Furthermore, plugins can react to certain kernel events, such as when the Di container is rebuilt, 
the kernel is booted, or even to include more bundles:
```
public function boot()
{
    parent::boot();
}

public function build(ContainerBuilder $container)
{
    parent::build($container);
}

public function registerBundles(string $environment): array
{
    return parent::registerBundles($environment);
}
```

## Include Services.xml
The central expansion option of a plugin is the 
[Di Container services](https://symfony.com/doc/current/service_container.html). 
In the core these are defined in XML. To integrate such a file as a plugin, 
the `build` function of the bootstrap file can be overwritten:
```
public function build(ContainerBuilder $container)
{
    parent::build($container);
    loader = new XmlFileLoader($container, new FileLocator(__DIR__));
    $loader->load('./DependencyInjection/services.xml');
}
``` 

## Entity Extension
Own entities can be integrated in the core via the corresponding `services.xml` 
and behave as described above. To extend existing entities, 
a `\Shopware\Framework\ORM\EntityExtensionInterface` can be used.
This must define for which entity the extension applies. 
Once this entity is accessed in the system, the extension can add more fields in the entity:
```
<?php

namespace GettingStarted\Content\Product;

use GettingStarted\Content\Promotion\PromotionDefinition;
use Shopware\Content\Product\ProductDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\Extension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PromotionExtension implements EntityExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [];
    }

    public function extendFields(FieldCollection $collection)
    {
        $collection->add(
            (new OneToManyAssociationField('promotion', PromotionDefinition::class, 'product_id', true))->setFlags(new Extension())
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
}
```
This example adds another association named `promotion` to the `ProductDefinition` class.