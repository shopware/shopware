[titleEn]: <>(Remove static methods from EntityDefinition)

We just removed a lot of static calls from DataAbstractionLayer. All `EntityDefinitions` are now nistances provided through the container. This changed a lot of internals and a few but **breaking** public API methods.

New rule of thumb: **If you need something from the `EntityDefinition` inject it**

### EntityDefinition

The `EntityDefinition` now must not contain any static methods.

```php
 public static function getEntityName() { ... }
```

Is now invalid and will throw a compile error from php. This now must be called

```php
public function getEntityName() {... }
```

Please adjust all method calls accordingly. (Usually `defineFields`, `getCollectionClass`, `getCollectionClass`)

### EntityRepository

The EntityRepositoryInterface now expects an instance of a Definition - as well as all subsequent Classes (Write, Read, ...). In order to ease the migration the repository now has a `getDefinition()` method to return the repositories definition if inspection or result rendering is necessary.

### ResponseFactoryInterface

All responses from custom actions rely on Entity definitions. Now you need to provide an instance of the EntityDefinition. You should be able to use the Repository you injected for most instances.

```php
/**
 * @var EntityRepositoryInterface
 */
private $orderRepository;

public function __construct(EntityRepositoryInterface $orderRepository) 
{
    $this->orderRepository = $orderRepository;
}
```

Calls to the `ResponseFactoryInterface` will now look like this:

```php
return $responseFactory->createDetailResponse(
    $orders,
    $this->orderRepository->getDefinition,
    $request,
    $context->getContext()
);
```




