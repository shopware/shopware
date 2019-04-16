[titleEn]: <>(Getting started with the platform)
[titleDe]: <>(Getting started with the platform)
[wikiUrl]: <>(../getting-started/platform?category=shopware-platform-en/getting-started)

## Platform structure

The platform is divided into three components. 

* Administration: Contains the administration written in Vue.js, administration related
commands, controller, the administration search and tests.
* Core: The heart of the platform. The core domain is divided into different subdomains:
    * Checkout: The checkout part includes all sources needed for the platform's checkout process.
          These include shopping cart calculation, customer administration, 
          ordering system, and the payment/shipping system.
    * Content: The content part contains sources that take care of the maintained contents of the platform.
          This includes product data, media, catalogs, categories, and configuration. 
          A look in this domain reveals which data can be served to different applications.
    * Framework: Contains the technical implementations of various components
          that are reused in the other domains like API, commands, events, exceptions, filesystem,
          migration system, DataAbstractionLayer, plugin system, price handling, routing, the rule system, search
          implementation, translation, template handling, and the versioning system.
    * Migration: Collection of all migrations. The database table `migration` is used 
          to keep track which migration have already been executed.
    * System: In the system part, entities are defined which are reused by the several other 
          domains. These include tax rates, configurations, currencies, and locales.
* Storefront: Responsive storefront written in HTML, JS (jQuery) using TWIG as template engine.
Since the platform is designed to work headless, the storefront is an individual and optional component.


## Getting started with the core domain
The core domain contains all sources which are required to run a headless e-commerce system.
It manages the system's data, provides an API and manages technical subtleties like
file system abstraction, dependency injection, checkout process, payment
methods or plugin system.

We use the Symfony 4 framework for the core. All data is stored in a configurable SQL environment 
and can be synchronized to storage engines like Redis or Elastic search to increase the performance.
The entire core is API Driven, meaning that all operations can be mapped through a corresponding API.
These include the normal CRUD operations of data as well as shopping cart calculations 
or even individual price calculations.

Furthermore, the core provides its own data abstraction layer,
specifically tailored for the requirements of the Shopware\Core universe. 
This makes it easy to (partially) change the storage engine, 
synchronize the data to other system and much more.

## Using the API
As already described, the entire Core is API driven. We offer three different types of API
to cover various use cases:

1. Admin API: Each entity defined by the data abstraction layer will automatically be
available via this API.
    Supported operations: GET, POST, PATCH, DELETE
    Route schema: /api/v{versionNumber}/{entityName}/
    Examples: 
    * GET /api/v1/product
    * GET /api/v1/product/64346348967843eb9638aed6fd0fee46
    * GET /api/v1/product/64346348967843eb9638aed6fd0fee46/manufacturer/
    
    Note: An authentication is required for this API.
2. Storefront API: Create, update and login customers, add line items to the cart, handle
payments, place orders and do various other storefront operations.
    Examples:
    * GET /sales-channel-api/checkout/cart
    * POST /sales-channel-api/checkout/cart/product/64346348967843eb9638aed6fd0fee46
3. Sync API: Create or update multiple entities at once.
    Examples:
    * POST /api/sync

For more information about how to get started with the API, checkout the [guide](../30-api/10-introduction.md).

### API Versioning
As already seen above, the API offers a versioning. This allows to implement
breaking changes and to make them available even though there are systems that still work
with old data formats or routes. One version number of the API will always support two
major versions of the platform. The API version v1 is therefore available simultaneously
with the version v2 and will be switched off with the release of the v3.
You will find out more about the API versioning later. 

### Context

The platform processes some user-, application- or environment specific information.
For example it might be important to know what language the user prefer to offer a
response which is correctly translated. In order to allow developers to work with this data,
different contexts are created during the boot process. Here is a list of context objects
and their properties:

* `Shopware\Core\Framework\SourceContext`
    * origin (api, storefront, system)
    * userId (optional)
    * integrationId (optional)
    * salesChannelId (optional)
* `Shopware\Core\Framework\Context` most common context, includes the `SourceContext`
    * languageId
    * fallbackLanguageId
    * versionId
    * sourceContext (see above)
    * catalogIds (optional)
    * currencyId
    * currencyFactor
    * rules
    * writeProtection

For the sake of completeness, there is an even more comprehensive context called `CheckoutContext`
which is not part of the getting started guide.

The platform usually assembles a context during the kernel boot so you don't have to create your own.
If you need a generic context for writing unit tests you can use:

```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\Context;

$context = Context::createDefaultContext();
``` 

Attention: Never assemble a generic context for production!

### Using the context inside a controller

If you write your own controller, you can just add the `Context` parameter
and the `Shopware\Core\Framework\Api\Context\ContextValueResolver` will
inject the right Context automatically.

```php
<?php declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class IndexController extends AbstractController
{
    /**
     * @Route("/", name="example")
     */
    public function index(Context $context): Response
    {
        // ...
    }
}
```

### Pagination

In the PHP stack, you can get access to the repository of an entity via the DI container.
Each repository uses its class name as DI container Id.

To search for entities you must supply a `Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria` object,
which defines a search query. Is this case we pass an empty Criteria,
thus no search is performed and all products in the database will be returned.

To limit the number of results returned, you can use the `setOffset` and `setLimit` methods.

**Example:**

```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

/** @var EntityRepositoryInterface $repository */
$repository = $this->container->get('product.repository');

$criteria = new Criteria();
$criteria->setOffset(0);
$criteria->setLimit(10);

$result = $repository->search($criteria, $context);
```

**API example request:** 

Each entity can also be accessed via API. Simple search queries can also be used via the 
corresponding entity routes. You can perform the same search as above by using the following route:
* `GET /api/v1/product?page=1&limit=10` 

Complex queries need to be sent via the `/search` endpoint:
* `POST /api/v1/search/product`

The `/search` endpoint supports complex searches. All operations of the
`\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria` class are supported.
The endpoint must be accessed via POST. The initial query would be expressed like this:
```php
<?php declare(strict_types=1);

$client = new \GuzzleHttp\Client();

$client->post(
    '/api/v1/search/product',
    [
        'body' => json_encode([
            'page' => 1,
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
* `\Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter`
    * Allows performing a string comparison (SQL: `LIKE`)
* `\Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter`
    * Query of a range of values (SQL: `<=`, `>=`, `>`, `<` )
* `\Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter`
    * Query to filter for an exact value
* `\Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter`
    * Query to filter a set of exact values (SQL: `IN`)
    
Note: Please do not use ContainsFilter for filtering on exact values on UUIDs.
At first sight, this might work but it has a negative impact on performance and can cause
unexpected behavior.

Using query containers you are able to combine or negate filter options:
* `\Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter`
    * Allows you to group multiple queries and associate them with an operator `AND` or `OR`
* `\Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter`
    * Allows negating queries

**Example:**

Let's start with a simple filtered list of products and filter products which are not active:
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

/** @var EntityRepositoryInterface $repository */
$repository = $this->container->get('product.repository');

$criteria = new Criteria();
$criteria->setOffset(0);
$criteria->setLimit(10);
$criteria->addFilter(new EqualsFilter('product.active', true));

$result = $repository->search($criteria, $context);
```

Filter only products which cost between € 100 and € 200:
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

$criteria = new Criteria();

$criteria->addFilter(
    new RangeFilter('product.price', [
        RangeFilter::GTE => 100,
        RangeFilter::LTE => 200
    ])
);
``` 

Next, only products are displayed where the manufacturer property `link` is defined:
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

$criteria = new Criteria();

$criteria->addFilter(
    new NotFilter(
        NotFilter::CONNECTION_AND,
        [new EqualsFilter('product.manufacturer.link', null)]
    )
);
```

Furthermore, only products with a minimum purchase amount of 1, 5 or 10 should be displayed:

```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

$criteria = new Criteria();

$criteria->addFilter(
    new EqualsAnyFilter('product.minPurchase', ['1', '5', '10'])
);
```

And last but not least, only products that have the letter `A` in their name should be displayed:
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;

$criteria = new Criteria();

$criteria->addFilter(
    new ContainsFilter('product.name', 'A')
);
```

**API example request:**

The same filter possibilities are also offered by the API. With a small difference: 
If you call the entity endpoint, only the `EqualsFilter` with the equal operator is supported.
Range queries or others are not possible. Example:
* `GET /api/v1/product?filter[product.active]=1&filter[product.manufacturer.name]=Shopware` 

For more complex filtering, use the `/search` endpoint as mentioned above. 
In the first example of filtering, filters were made for products that have the active flag. 
So a EqualsFilter must also be sent via the API:
```php
<?php declare(strict_types=1);

use GuzzleHttp\Client;

$client = new Client();

$client->post(
    '/api/v1/search/product',
    [
        'body' => json_encode([
            'page' => 1,
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
using a range query. With range query, you have to pass the corresponding operators
like `greater than equals`, `less than` as `parameters`. Example:
```php
<?php declare(strict_types=1);

use GuzzleHttp\Client;

$client = new Client();

$result = $client->post(
    '/api/v1/search/product',
    [
        'body' => json_encode([
            'page' => 1,
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
This was solved by using a negation. It works for the API as well by using a `NOT` query,
which contains the other queries and negates them:
```php
<?php declare(strict_types=1);

use GuzzleHttp\Client;

$client = new Client();

$result = $client->post(
    '/api/v1/search/product',
    [
        'body' => json_encode([
            'page' => 1,
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

The next step was to limit the list to products with a minimum purchase amount of 1, 5 or 10. 
The `terms` query is used for this:
```php
<?php declare(strict_types=1);

use GuzzleHttp\Client;

$client = new Client();

$result = $client->post(
    '/api/v1/search/product',
    [
        'body' => json_encode([
            'page' => 1,
            'limit' => 10,
            'filter' => [
                [
                    'type' => 'terms',
                    'field' => 'product.minPurchase',
                    'value' => ['1', '5', '10']
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

And finally, let's filter products that have the letter `A` in their name.
This is done by using `match` queries:
```php
<?php declare(strict_types=1);

use GuzzleHttp\Client;

$client = new Client();

$result = $client->post(
    '/api/v1/search/product',
    [
        'body' => json_encode([
            'page' => 1,
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


### Keep the system fast - A short detour into performance optimization
The examples above explained how to use criteria objects and filters to restrict the search result.
Sometimes you already now the Ids you want to search for because the user provided the ids or you
already did a search and now want to read an association.

This section is a short detour in the area of performance,
which is extremely important when it comes to maintaining a high-performance system:
Suppose you have to select 15 products on a system and we just got the Ids back from the repository.
Now there are several ways to determine the data for these 15 products. 
The following script shows two ways:
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/** @var RepositoryInterface $repository */
$repository = $this->container->get('product.repository');
 
$criteria = new Criteria();
$criteria->setLimit(15);

$ids = $repository->searchIds(
    $criteria, 
    $context
);

// batch variant
$basics = $repository->read(
    new Criteria($ids->getIds()), 
    $context
);


// loop variant
foreach ($ids->getIds() as $id) {
    $basics = $repository->read(
        new Criteria([$id]), 
        $context
    );
}
```
In the above example, the *LOOP variant* for reading the 15 products is **three times slower** compared to the *batch variant*. 
Therefore, you should always keep in mind during development to load data collected,
since the *batch variant* scales a lot better than the *loop variant*. 
If you increase the limit to 50 in the example above, the loop is **six times slower** than the batch variant.

### API Read 
To read a specific list of products via the API, you should use the `/search` endpoint
and restricting it to the corresponding Ids with a term query:
```php
<?php declare(strict_types=1);

use GuzzleHttp\Client;

$client = new Client();

$result = $client->post(
    '/api/v1/search/product',
    [
        'body' => json_encode([
            'page' => 1,
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
All write operations are accepted by the DataAbstractionLayer as a batch operation
and all associated data can be processed in the same operation. The following three functions 
allow you to write data: 
* `update(array $data, Context $context): EntityWrittenContainerEvent;`
    * Updates records. If a record does not exist, an exception is thrown
* `create(array $data, Context $context): EntityWrittenContainerEvent;`
    * Creates records. If a record already exists, an exception is thrown
* `upsert(array $data, Context $context): EntityWrittenContainerEvent;`
    * Creates or updates records. If the record does not exist, it will be created, 
      if it already exists, it will be updated.

**Repository example**
The platform does not work with entity/model classes when writing data.
Instead, the platform works with simple arrays. This has the advantage,
that no read operation must take place before the data is written:
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

/** @var RepositoryInterface $repository */
$repository = $this->container->get('product.repository');

$id = Uuid::randomHex();

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
    $context
);
```
In the example above, a new product is created. At the same time, a new tax rate 
and a new manufacturer will be created because they were not sent with a corresponding Id.

To link an existing manufacturer or tax rate, you can simply supply the corresponding foreign key:
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

/** @var RepositoryInterface $repository */
$repository = $this->container->get('product.repository');

$repository->upsert(
    [

        [
            'name' => 'Test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturerId' => '034baca6d92641e89db8df9dddc1b30d',
            'taxId' => '4926035368e34d9fa695e017d7a231b9'
        ]
    ],
    $context
);
```

In order to link an existing manufacturer or tax rate and to update it at the same time, 
the corresponding foreign key must be sent along with the associated data array:
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

/** @var RepositoryInterface $repository */
$repository = $this->container->get('product.repository');

$id = Uuid::randomHex();

$repository->upsert(
    [
        [
            'id' => $id,
            'name' => 'Test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['id' => '034baca6d92641e89db8df9dddc1b30d', 'name' => 'test'],
            'tax' => ['id' => '4926035368e34d9fa695e017d7a231b9', 'name' => 'test', 'rate' => 15]
        ],
    ],
    $context
);
```
 
**API example request**

The API also provides these three ways to write entities. 
These are reflected in the HTTP methods:
* POST  `/api/v1/product` - `create`
* PUT   `/api/v1/product/{id}` - `update`
* PATCH `/api/v1/product` - `upsert`

All three functions have the same syntax. For update operations, it is not necessary to resend
the entire entity. It is possible to send only the corresponding changeset.
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\Uuid\Uuid;
use GuzzleHttp\Client;

$client = new Client();

$id = Uuid::randomHex();

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

## Defining an Entity
An entity defines the data structure of a table in the system. The platform uses
type hinted `Entity` classes to provide autocompletion and offer an
interface for developers on which they can rely.

The properties in the PHP code are usually mapped to corresponding columns
in the table of an entity and additional sanity checks (like a tests for emptiness) 
are performed on them.

The core works a bit differently with entities than you might expect. First of all, there is no
entity or model class. Column mappings are also not implemented with annotations.
Instead, an `EntityDefinition` class for each entity is required.  
In this class, the corresponding columns and properties of an entity are defined as well as 
associated classes such as events, entities, translations, collections, etc.

Once an entity definition has been created with the associated classes, 
it can be selected via the data abstraction layer, events are thrown
for the loaded objects and the entity is automatically available via the API.

All classes for an entity are in the corresponding domain folder. 
Each of these domains has the following structure:
* Aggregate - *Includes subordinate entities (e.g. `ProductTranslation`,` ProductCategory`)*
* Exception - *Contains all exceptions that occur when working with the entity*
* {EntityName}Definition.php - *The corresponding definition class*

### EntityDefinition class
In an entity definition class, the following information is recorded:
* Which fields does the entity consist of?
* Which DTO (Entity & Collection) classes belong to the entity?

Let's begin with an empty entity definition:

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'product';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([]);
    }
}
```
First, the `getEntityName` function defines which table this class refers to
in this example `product`.
The `defineFields` method returns a `FieldCollection` which facilitates working with entity fields.

#### Adding fields to an entity
Now you can fill the entity with fields. Let's start with simple fields:
 
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;

protected static function defineFields(): FieldCollection
{
    return new FieldCollection([
        new StringField('ean', 'ean'),
        new BoolField('active', 'active'),
        new IntField('stock', 'stock'),
        new FloatField('weight', 'weight'),
        new CreatedAtField(),
    ]);
}
```
These are the available scalar data types which are supported. 
Each of the fields gets passed two parameters: `Column name` &` Property name`.
The first is used to identify the field in the database table, 
the latter is used in PHP.

Thus the entity now has the following properties to work with:
* `ean` 
* `active`
* `stock`
* `weight`
* `createdAt`

#### Defining the primary key
Now let's add a primary key for the entity that allows to identify records of that entity 
to delete or update them:
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;

protected static function defineFields(): FieldCollection
{
    return new FieldCollection([
       (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),  
       // ...
    ]);
}
```
In the example above an `IdField` with the flags `PrimaryKey` and` Required` is added. 
Field flags allow certain constraints to be added to fields. The `PrimaryKey` flag used here
defines that this field is part of the primary key.
The `Required` flag, on the other hand, defines that this value must be set during writing. 
The platform uses UUIDs in the v4 standard as primary keys. This has the advantage of being able to 
build entities together and cross-reference them before they are written to the database.

#### Define a translatable field
The previously created data has been defined as simple data types and does not offer the
possibility to be translated. 
Translatable data is always stored in a separate table.

The corresponding translation table always has the suffix `_translation`. 
Since the name of the entity in the example is `product`, 
the name of the translation table is `product_translation`. 

This table also has its own entity definition, but this is beyond the scope of this example.
If a field should be translatable, use the `TranslatedField`:
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;

protected static function defineFields(): FieldCollection
{
    return new FieldCollection([
       // ...
       new TranslatedField('name')
       // ...
    ]);
}
```
The `TranslatedField` automatically maps to the underlying field with the property name `name`
in the translated definition. The actual `Field` is defined there.

#### Foreign keys
If you want to link two entities you should use a foreign key. 
To assign a manufacturer to our previously defined entity such a key is used to 
store the ID of the manufacturer.

A foreign key is used to define that the value of this field refers
to a record in another table. This allows to create links between tables/entities and ensure
data consistency.

To ensure that only existing manufacturer Ids can be stored, the data abstraction layer
should do a cross-check. To enable this, you have to use the `FkField`.
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;

protected static function defineFields(): FieldCollection
{
    return new FieldCollection([
       // ...
       new FkField('product_manufacturer_id', 'manufacturerId', ProductManufacturerDefinition::class),  
       // ...
    ]);
}
```
Again, the first two parameter correspond to the `Column name` and `Property name` 
of the newly defined field.
The third parameter expects the `FkField`(the corresponding reference to the `EntityDefinition`).
As a result, the system recognizes that the stored foreign key is a manufacturer record 
and checks it accordingly when writing the record.

#### Associations
Although the product entity now contains a foreign key field, it is not yet mapped
to the key field of the manufacturer. In order to do this you need to define an `AssociationField`.

. The following associations can be defined:
* `OneToManyAssociationField`
* `ManyToManyAssociationField`
* `ManyToOneassociationField`

In case of the product-manufacturer relationship, a `ManyToOneAssociationFiel` is needed since
one product is produced by only one manufacturer, but a manufacturer can
produce many different products.

The parameters of an association are described in the following example:
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;

protected static function defineFields(): FieldCollection
{
    return new FieldCollection([
       // ...
       new ManyToOneAssociationField(
            // name of the property
            'manufacturer',
            
            // storage name of the foreign key                         
            'product_manufacturer_id', 
            
            // reference definition class
            ProductManufacturerDefinition::class,
            
            // should be loaded by default 
            false,
            
            // reference storage name
            'id'
        ),
       // ...
    ]);
}
```

By defining this association, it's possible to work with the product and 
the manufacturer at the same time.

You can always address this association via the property name:
* To filter in the search - `new EqualsFilter('product.manufacturer.name', 'shopware')`
* To create a manufacturer directly when writing the product - `'manufacturer' => ['name' => 'shopware']`
* To determine the number of manufacturers of a product list - `new CountAggregation('product.manufacturer.id', 'manufacturer_count')` 
* To read the manufacturer of a product via API - `GET /api/v1/product/{id}/manufacturer`
* ...

## Entity classes
To transport the data between the database and the corresponding endpoints, the core uses
entity classes. These are simple PHP classes in which the properties of an entity definition are
defined as PHP properties and are available via getter/setter functions.
The `Entity` class contains all the properties of an entity definition defined and the relations.
Be aware, most of these values are nullable and they might not be loaded by default to
improve the performance. In general, all properties of the entity are loaded
and the associations are not. So you have a minimum set of properties in order
to work with the entity.



A simple entity class can look like this:
```php
<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class LocaleEntity extends Entity
{
    use EntityIdTrait;

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

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
```

### Collection classes
The repository classes of the core do not return arrays with entity classes but
a type-aware collection class which contain all elements. These classes can 
be iterated to easily handle all records:
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

$ids = [
    '3aa78661e1ab4a2b94bb5e6ea85fdba7',
    '71d5b7df613f44e2a96ca3190a2b8173',
];

/** @var EntityRepositoryInterface $repository */
$repository = $this->container->get('product.repository');

$products = $repository->read(new Criteria($ids), $context);

foreach ($products as $product) {
    echo $product->getName();
}
```
In addition, the collections provide small helper functions allow an easy access to
the collection's aggregated data:
```php
<?php declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Content\Product\ProductCollection;

$ids = [
    '3aa78661e1ab4a2b94bb5e6ea85fdba7',
    '71d5b7df613f44e2a96ca3190a2b8173',
];


/** @var EntityRepositoryInterface $repository */
$repository = $this->container->get('product.repository');

/** @var ProductCollection $products */
$products = $repository->read(new Criteria($ids), $context);

$taxes = $products->getTaxes();

$ids = $products->getIds();

```

### Event classes
The core uses the  
[Symfony Event System](https://symfony.com/doc/current/components/event_dispatcher.html). 
Additionally, entity-related events are thrown, including:

| Event | Description |
|---|---|
| `product.written` | After the data has been written to storage |
| `product.deleted` | After the data has been deleted in storage |
| `product.loaded` | After the data has been hydrated into objects |
| `product.search.result.loaded` | After the search returned data |
| `product.aggregation.result.loaded` | After the aggregations have been loaded |
| `product.id.search.result.loaded` | After the search for ids only has been finished |

More information about events can be found in the [events guide](../20-data-abstraction-layer/8-events.md).

## Write your first plugin

Read more about plugin development in the [plugin starting guide](../60-plugin-system/01-getting-started.md).
