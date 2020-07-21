[titleEn]: <>(API versioning)
[hash]: <>(article:api_versioning)

The Admin-API and Sales-Channel-API are versioned. That means whenever a breaking change to the API occurs we release a new API version.
We may release a new API version with every major release, but the old API version stays supported until the next major release, e.g. 6.2.0 or 6.3.0.

The API version you want to use is determined by the API-Route you call, e.g. `/api/v3/...` for version 1 of the Admin-API or `/sales-channel-api/v2` for version 2 of the SalesChannelApi.

The currently available API versions are available as a container parameter under `kernel.supported_api_versions`.

## Changing the Entity-Schema

As the entities defined in the DataAbstractionLayer are automatically exposed through the API in the CRUD actions we have to take the Entity-Schema into account when talking about API-Versioning.

The handling of entities in different version is done through `ApiConverters`. The abstract `ApiConverter` class provides four Methods you have to implement:

```php
namespace Shopware\Core\Framework\Api\Converter;

abstract class ApiConverter
{
    abstract public function getApiVersion(): int;

    abstract protected function getDeprecations(): array;
    
    abstract protected function getNewFields(): array;

    abstract protected function getConverterFunctions(): array;
}
```

The `getApiVersion()` method should return the version of the Api that this Converter can handle. A sample implementation may look like this:
```php
public function getApiVersion(): int 
{
    return 2;
}
```

In the `getDeprecations()` method you have to define all fields and entities that are no longer accessible in the version of the API returned by the `getApiVersion()` method.
The method returns an array that is indexed by the entity name and has the deprecated fields as values. If you want to deprecate the whole entity just use `true` as the value for the entity name.
An implementation may look like this:
```php
protected function getDeprecations(): array 
{
    return [
        'product' => [
            'name',
        ],
        'manufacturer' => true
    ];
}
```

Accordingly the `getNewFields()` method returns the entities and fields that were introduced in this API versio .
It uses the same format as the `getDeprecations()` method, so it may look like this:
```php
protected function getNewFields(): array 
{
    return [
        'product' => [
            'nameV2',
        ],
        'manufacturerV2' => true
    ];
}
```

In the `getConverterFunctions()` method you can define you payloads can be converted from the old API version to the new one.
You can define one converter function per entity.
An example may look like this:
```php
protected function getConverterFunctions(): array
{
    return [
        'product' => function (array $payload): array {
            if (array_key_exists('name', $payload)) {
                $payload['nameV2'] = $payload['name'];
                unset($payload['name']);
            }

            return $payload;
        }
    ];
}
```

So a full example of an `ApiConverter` implementation looks like this:
```php
use Shopware\Core\Framework\Api\Converter\ApiConverter;

class V2ApiConverter extends ApiConverter
{
    public function getApiVersion(): int 
    {
        return 2;
    }

    protected function getDeprecations(): array 
    {
        return [
            'product' => [
                'name',
            ],
            'manufacturer' => true
        ];
    }
    
    protected function getNewFields(): array 
    {
        return [
            'product' => [
                'nameV2',
            ],
            'manufacturerV2' => true
        ];
    }

    protected function getConverterFunctions(): array
    {
        return [
            'product' => function (array $payload): array {
                if (array_key_exists('name', $payload)) {
                    $payload['nameV2'] = $payload['name'];
                    unset($payload['name']);
                }
    
                return $payload;
            }
        ];
    }
}
```

The last thing we have to do is register our Converter in the container and tag it with the `shopware.api.converter` tag.
```xml
<service id="MyPlugin\Converters\V2Converter">
    <tag name="shopware.api.converter"/>
</service>
```

Additionally you have to mark all fields that are removed from the API as deprecated in the DAL. This is done through the `Deprecated` flag.
```
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;


class ProductDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'product';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('name', 'name'))->addFlags(new Deprecated('v1', 'v2')),
        ]);
    }
}
```

Don't forget to annotate the getter and setter for the deprecated fields in the `Entity` class with the `@deprecated` annotation.

## Requesting Entities in different versions

When you use the API for example in API version 2 you can write/filter/request all Fields that are available in this version. 
Fields that are not available are those that are marked as deprecated in Version 2 of the API, or those that are introduced with a future version of the API.

When you try to write those fields or use them in a filter for example you will get an error. Also all not available fields get stripped from the responses.

So a request to `POST /api/v3/product` with payload
```json
{
    "id": "01bd7e70a50443ec96a01fd34890dcc5",
    "nameV2": "Example product"
}
```
will return a status code of 400 with the error code `FRAMEWORK__WRITE_FUTURE_FIELD`, because the `nameV2` field was added in v2 but we want to write a product in v1 of the API.


Similarly requesting `POST /api/v2/product` with payload
```json
{
    "id": "01bd7e70a50443ec96a01fd34890dcc5",
    "name": "Example product"
}
```
will return a status code of 400 with the error code `FRAMEWORK__WRITE_REMOVED_FIELD`, because the `name` field was removed in v2 of the API.

If we request the entity in v1 `GET /api/v3/product/01bd7e70a50443ec96a01fd34890dcc5` we will get the following response:
```json
{
    "data": {
        "id": "01bd7e70a50443ec96a01fd34890dcc5",
        "name": "Example product",
        ...
    }
}
```

In v2 `GET /api/v2/product/01bd7e70a50443ec96a01fd34890dcc5` the response would look like this:
```json
{
    "data": {
        "id": "01bd7e70a50443ec96a01fd34890dcc5",
        "nameV2": "Example product",
        ...
    }
}
```

## Versioning custom api routes

All custom action routes have to care about versioning too. First all Routes have to accept the version parameter as a route parameter, so all routes have to follow the following schema:
 * Admin-API routes have to start with `api/v{version}`
 * Sales-Channel-Api routes have to start with `sales-channel-api/v{version}`
 
### Add a new version of a route

If you add a breaking change to an API route you have to make sure that the old behavior in the old API version does not change.

For example if you imagine a simple controller like this:
```php
/**
 * @Route("/api/v{version}/_action/do-something", name="api.do.something", methods={"POST"}, requirements={"version"="\d+"})
 */
public function doSomething(Context $context): Response
{
    // do something

    return new JsonResponse(['sucess' => true]);
}
```

Now you want to indicate the success of the operation by the HTTP status code, instead of the success field in the response.
This is a breaking change, as old clients still check the success flag.
You now have to create two actions one for the old version and one for the current and the upcoming versions.
First you change the `v{version}` wildcard in your old action, to the hard-coded version for which this implementation of the route should be used, e.g. `v1` and mark your action with the `@deprecated` tag to be removed in a upcoming release.
```php
/**
 * @Route("/api/v3/_action/do-something", name="api.do.something.v1", methods={"POST"}, requirements={"version"="\d+"})
 * @deprecated tag:v6.2.0
 */
public function doSomethingV1(Context $context): Response
{
    // do something

    return new JsonResponse(['sucess' => true]);
}
```

After this change you can implement your new version of the route and use the version wildcard again. For the routing to work make sure to add your new action below the old version, so the old route will match first.
```php
/**
 * @Route("/api/v{version}/_action/do-something", name="api.do.something", methods={"POST"}, requirements={"version"="\d+"})
 */
public function doSomething(Context $context): Response
{
    // do something

    return new JsonResponse(null, 204);
}
```

### Returning entities
 
If you return entities or search results from your api route you also have to take care of converting the entities between versions, as described above.
Therefore you can use the `ApiVersionConverter::convertEntity()`-method:
```php
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;


class MyController extends AbstractController
{
    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    public function __construct(
        EntityRepositoryInterface $productRepository,
        ApiVersionConverter $apiVersionConverter
    ) {
        $this->productRepository = $productRepository;
        $this->apiVersionConverter = $apiVersionConverter;
    }
    
    /**
     * @Route("/api/v{version}/_action/product/{id}", name="api.product.get", methods={"GET"}, requirements={"version"="\d+"})
     */
    public function getProductById(string $id, int $version, Context $context): Response
    {
        $entity = $this->productRepository->search(new Criteria([$id]), $context)->get($id);
        $entity = $this->apiVersionConverter->convertEntity(
            $this->productRepository->getDefinition(),
            $entity,
            $apiVersion
        );

        return new JsonResponse(['data' => $entity]);
    }
}
```
