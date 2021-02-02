# 2020-11-25 - Decoration pattern

## Context

There are currently two different patterns that are applied in the platform to allow the decoration of services. 
On the one hand there are the interfaces and on the other hand the abstract classes. 

With this ADR it should be decided that we don't implement interfaces anymore, because they are too strict.

There are two reasons why we should no longer implement interfaces.

### It is much more complicated to add more parameters for a function of the interface
To add another parameter to a function or interface, proceed as follows:

* The additional parameter is documented in the interface

```php
interface DataValidationFactoryInterface
{
    /**
     * @param array $data - @deprecated tag:v6.4.0 - Will be introduced in v6.4.0
     */
    public function create(SalesChannelContext $context /* array $data */): DataValidationDefinition;
}
```

* In the implementation, the parameter can be accepted as follows:

```php
class ContactFormValidationFactory implements DataValidationFactoryInterface
{
    public function create(SalesChannelContext $context  /* array $data */): DataValidationDefinition
    {
        $data = func_get_arg(1) ?? [];
    }
}
```

As you can see, it is possible, but this is more "beautiful" with abstract classes:

```php
abstract class AbstractCustomerRoute
{
    /**
     * @deprecated tag:v6.4.0 - Parameter $criteria will be mandatory in future implementation
     */
    abstract public function load(Request $request, SalesChannelContext $context/*, Criteria $criteria*/): CustomerResponse;
}

class CustomerRoute extends AbstractCustomerRoute
{
    public function load(Request $request, SalesChannelContext $context, ?Criteria $criteria = null): CustomerResponse
    {
    }
}
```

### It is not possible to provide further functions in the class 
If we have to implement another function in an interface this is only possible in a very complicated way.
* a new interface is implemented which extends the old one:
```php
interface DataValidationFactoryInterface
{
    public function create(SalesChannelContext $context /* array $data */): DataValidationDefinition;
}

interface DataValidationFactoryInterfaceV2 extends DataValidationFactoryInterface
{
    public function update(SalesChannelContext $context /* array $data */): DataValidationDefinition;
}
```

* At the appropriate place where the class is called, it is checked if the instance implements the new interface:

```php
if ($service instanceof DataValidationFactoryInterfaceV2) {
    $service->update(..)
} else {
    $service->create(..)
}
```

However, errors occur here if several plugins decorate this service. If one of the plugins does not yet implement the new interface, there is a PHP error.
With the pattern of the abstract classes this looks differently. Here a fallback is provided by the `getDecorated()` method. Plugins which do not support the new function yet, are virtually skipped in the decoration chain.

```php
abstract class AbstractCustomerRoute
{
    abstract public function load(Request $request, SalesChannelContext $context): CustomerResponse;

    abstract public function getDecorated(): AbstractCustomerRoute; 

    public function loadV2() 
    {
        $this->getDecorated()->loadV2();                               
    }       
}
```

At the appropriate place where the class is called, we can simply use the new function without checking if the current service instance already contains the new method:
```php
$service->loadV2(..);
```

## Decision

* In the platform we no longer use interfaces for service definitions. Especially not if this service is intended for decoration.
* For other cases we use abstract classes as well because we can easily extend or change signatures.

## Consequences
* We replace, iteratively, the existing interfaces that are marked as not @internal with abstract classes
* The abstract class must implement the interface for backward compatibility
* Once an equivalent for the interface exists, it will be deprecated and removed with the next major version
* The abstract class is always used as type hint for constructors or parameters.
