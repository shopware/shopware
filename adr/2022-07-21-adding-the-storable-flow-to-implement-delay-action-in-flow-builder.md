---
title: Adding the `StorableFlow` instead of the `FlowEvent` for implementing the flow DelayAction in flow builder
date: 2022-07-21
area: services-settings
tags: [flow, event, refactoring]
---

## Context

The actions in Flow Builder are listening for Business Events. We want to implement the flow DelayAction in Flow Builder,
that means the actions can be delayed, and will be executed after  a set amount of time.
But we have some problems after the action was delayed:
* Events may contain old data, that data may be updated during the delay, and currently we don't have any way to restore
    the data.
* After a delay the rules have to be re-evaluated, but the data in the rules could be outdated or changed, so the rules
    have to be reloaded as well. Or the rules do not exist anymore.

## Decision

We would need to detach the Event System and the Flow System from each other, thus removing the dependency on the runtime objects within an event.
Meaning the Actions must not have access to the original Events.

We would create a class `StorableFlow`, that can store the data in the original event as scalar values, and restore the original data based on this stored data.
```php
class StorableFlow
{
    // contains the scalar values based on the original events
    // $store can be serialized and used to restore the object data
    protected array $store = [];
    
    // contains the restored object data like the data we defined in the `availableData` in original events 
    // $data can not be serialized, but can be restored from $store
    protected array $data = [];
      
    public function __construct(array $store = [], array $data = [])
    {
        $this->store = $store;
        $this->data = $data;
    }
    
    // This method will be called in each `Storer` to store the representation of data
    public function setStore(string $key, $value) {
        $this->store[$key] = $value;
    }
    
    public function getStore(string $key) {
        return $this->store[$key];
    }
    
    // After we restored the data in `Storer`, we can set the data, we'll use `$this->data` instead getter data on original event
    public function setData(string $key, $value) {
        $this->data[$key] = $value;
    }
    
    public function getData(string $key) {
        return $this->data[$key];
    }
}
```

The `StorableFlow` class will be use on Flow Builder:

Before:

```php
class FlowDispatcher 
{
    public function dispatch(Event $event) {
        ...
        // Currently, dispatch on Flow Builder use the original event to execute the Flow 
        $this->callFlowExecutor($event);
        ...
    }
}
```

After:

```php
class FlowDispatcher 
{
    public function dispatch(Event $event) {
        ...
        // The `FlowFactory` will create/restore the `StorableFlow` from original event
        $flow = $this->flowFactory->create($event);
        // use the `StorableFlow` to execute the flow builder actions instead of the original events
        $this->execute($flow);
        ...
    }
}
```

* Flow Builder actions may no longer access the original event.
* Each Aware Interface gets its own `Storer` class to restore the data of Aware, so we have many `Storer` like `OrderStorer`, `MailStorer`, `CustomerStorer` ...
* The main task of a `Storer` is to restore the data from a scalar storage.
* The `Storer` provides a store function, in order to store itself the data, in order restore the object
* The `Storer` provides a restore function to restore the object using the store data.

```php
interface FlowStorer {}
```
Example for `OrderStorer`:

```php
class OrderStorer implements FlowStorer
{
    // This function to check the original event is the instanceof Aware interface, and store the representation.
    public function store(FlowEventAware $event, array $storedData): array 
    {
        if ($event instanceof OrderAware) {
            $storedData['orderId'] = $event->getOrderId();
        }
        
        return $storedData;
    }
    
    // This function is restore the data based on representation in `storedData`
    public function restore(StorableFlow $flow): void
    {
        if ($flow->hasStore('orderId')) {
            // allows to provide a closure for lazy data loading
            // this opens the opportunity to have lazy loading for big data
            // When we load the entity, we need to add the necessary associations for each entity
            $flow->lazy('order', [$this, 'load']);    
        }
        ...
    }
}
```

About the additional data defined in `availableData` in original events, that aren't defined in any Aware Interfaces and we can't restore that data in the `Storer`.
To cover the additional data from original events, we will have another `store` `AdditionalStorer` to store those data.
```php
class AdditionalStorer extends FlowStorer
{
    public function store(FlowEventAware $event, array $storedData)
    {
        ...
        // based on the `getAvailableData` in the original event to get the type of additional data
        $additionalDataTypes = $event::getAvailableData()->toArray();
        
        foreach ($additionalDataTypes as $key => $eventData) {
            // Check if the type of data is Entity or EntityCollection
            // in the $storedData, we only store the presentation like ['id' => id, 'entity' => entity], we'll restore the data in `AdditionalStorer::restore`
            if ($eventData['type'] === 'Entity' || 'EntityCollection') {
                $storedData[$key] = [
                    'id' => $event->getId(),
                    'entity' => Entity                 
                ];
            }
            
            // Check if the type of data is ScalarValueType
            if ($eventData['type'] === ScalarValueType) {
                $storedData[$key] = value
            }
            
            // start to implement /Serializable for ObjectType
            if ($eventData['type'] === ObjectType) {
                $storedData[$key] = value->serialize()
            }
            
            ...
        }
        
        ... 
        
        return $storedData;
    }
      
    // this function  make sure we can restore the additional data from original data are not covered in `Storer`
    // The additional data can be other entity, because the entities we defined in Aware interface like `order`, `customer` ... covered be `Storer`
    public function restore(StorableFlow $flow): void
    {
        if (type === entity) {
            // About the associations for entity data, mostly the additional entity data is the base entity, we don't need to add associations for this
            $flow->setData($key, $this->load());
        } else {
            $flow->setData($key, $flow->getStore($key));
        }
        ...
    }
}
```

About the associations for entity data, mostly the additional entity data is the base entity, we don't need to add associations for this.
About the `ObjectType` data, we enforce all values used in ObjectType implement /Serializable, and serialize the object before store to `$storedData`.

* Flow Builder actions only work with the `StorableFlow` instead of the `FlowEvent`. The `StorableFlow` will restore the data from original events via `Storer`,
  and the Actions can get the data via `getData($key)` from `StorableFlow` instead of `getAvailableData` from original events.

Before, in the flow actions still dependency Aware interfaces:

```php
    public function handle(StorableFlow $event) {
        ...
        $baseEvent = $event->getEvent();
    
        if ($baseEvent instanceof CustomerAware) {
            $customerId= $baseEvent->getCustomerId();
        }
        ...
    }
```
After in the flow actions:
```php
    public function handle(StorableFlow $event) {
        ...
        if ($event->hasStore('customerId') {
            $customerId= $event->getStore('customerId');
        }
        ...
    }
```

* `getAvailableData` must NOT be responsible for the access of the data.
* To create new or restore the `StorableFlow` by on the existing stored data, we need to provider the `FlowFactory`.
```php
class FlowFactory
{    
    ...
    public function create(FlowEventAware $event)
    {
        $storedData = [];
        foreach ($this->storer as $storer) {
            // Storer are responsible to move the corresponding 
            // data from the original event 
            $storer->store($event, $storedData);
        }
        
        return $this->restore($storedData);
    }
  
    public function restore(array $stored = [], array $data = [])
    {
        $flow = new StorableFlow($stored, $data);
      
        foreach ($this->storer as $storer) {
            $storer->restore($flow);
        }
  
        return $flow;
    }
    ...
}
```
But when executing a delayed actions, we won't have a `StorableFlow`, we just have the `$stored` from the previously stored `StorableFlow`,
and based on the `$stored`, we can restore a new `StorableFlow`.

Example in Delay Actions:

```php
// In handler delay actions -> put the actions to `queue`
$stored = json_encode($flow->stored());

$connection->executeStatement('INSERT INTO `swag_delay_action` (store) VALUES (:stored)...', ['stored' => $stored]);
```

```php
// In handler execute delay actions
$stored = 'SELECT store FROM `swag_delay_action` .... ';

$flow = $this->flowFactory->restore(json_decode($stored));
```

## Consequences

Because we use the new class `StorableFlow` instead of the `FlowEvent` class in the Flow Builder, we cannot use the original
events or aware interfaces anymore, but about the symfony event was listeners the `FlowEvent`, those can continue to
use the interfaces as the store is not yet filled during we'll remove it in next major version.
* In symfony event listeners: only use the interfaces as the store is not yet filled 
* In the flow builder: Only use the store functionality as the interfaces might not be implemented
