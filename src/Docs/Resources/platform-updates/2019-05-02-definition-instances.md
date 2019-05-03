[titleEn]: <>(Remove static methods from EntityDefinition)

We just removed a lot of static calls from DataAbstractionLayer. All `EntityDefinitions` are now instances provided through the container. This changed a lot of internals and a few but **breaking** public API methods.

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

Please adjust all method calls accordingly. (Usually `getEntityName`, `defineFields`, `getCollectionClass`, `getEntityClass`)

### EntityDefinition service declaration

The service definition tags already had a `entity="entity_name""` property. From now on this is required and the build will fail if it's not provided.

```xml
<service id="Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountRule\PromotionDiscountRuleDefinition">
    <tag name="shopware.entity.definition" entity="promotion_discount_rule"/>
</service>
```

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

### ::class references

The dependency injection container secures that a particular instance of a definition is created only once per request. If you need equality checks please use the strict comparison operator on the objects themselves. `$assiciation->getReferenceDefinition() === $definition` works and is the recommended way.

### SalesChannel-API

The SalesChannel-API's implementation got revamped. Since the definitions themselves are instances now, the SalesChannel-API is now an entirely second cluster of instances in memory that does not touch the original instances. By that we removed the `SalesChannelDefinitionTrait` and calls to `decorateDefinitions` are no longer available. Everything will be injected automatically through the container at compile time.

This is achieved through a decorated registry. Object comparsion with the `SalesChannelDefinitionInstanceRegistry` yields different results from the base registry. 

```php
// Sales channle entities are different classes
$salesChannelProductDefinition instanceof $sproductDefinition // === true
$productDefinition instanceof $salesChannelProductDefinition // === false

// The decorated registry allways returns the sales channel object, regardless of the provided service id
$salesChannelRegistry->get(ProductDefinition::class) instanceof $slaesChannelRegistry->get(SalesChannelProductDefinition::class) // == true
$salesChannelRegistry->get(ProductDefinition::class) === $slaesChannelRegistry->get(SalesChannelProductDefinition::class) // == true
```

This replacement is done based on the `entityName` so overwriting a base definition takes a service declaration like this:

```xml
<service id="Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition">
    <tag name="shopware.sales_channel.entity.definition" entity="product"/>
</service>
```

Overwrites the product definition in SalesChannel-API requests.

### Internal changes

The change to instances brought many changes to internal implementations in the DataAbstractionLayer. Conceptually the whole configuration now relies on a **compile step** that is automatically triggered by the container on object creation.

All Definitions and Fields now refer to concrete instances of EntityDefinition as opposed to the class reference. By that you can navigate the entire Object tree without using any registry or container calls:

```php
$productDefinition // ProductDefinition
    ->getFields() // CompiledFieldCollection
    ->get('categories') // ManyToManyAssociationField
    ->getToManyDefinition() // CategoryDefinition
    ->getFields() // CompiledFieldCollection
    ->get('tags') // ManyToManyAssociationField
    ->getToManyDefinition() // TagDefinition
```

#### Removal of *Registries

The formerly necessary `FieldSerializerRegistry`, `FieldAccessorBuilderRegistry` and `FieldResolverRegistry` were removed in favor of getters on the field class. The fields lazily acquire these objects from the service container through the `DefinitionInstanceRegistry`.

```php
class Field
{
    ...

    public function getSerializer(): FieldSerializerInterface { ... }

    public function getResolver(): ?FieldResolverInterface { ... }

    public function getAccessorBuilder(): ?FieldAccessorBuilderInterface { ... }
    
    ...
}
```
