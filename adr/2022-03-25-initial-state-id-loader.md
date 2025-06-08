---
title: Initial state id loader
date: 2022-03-25
area: customer-order
tags: [performance, state-machine, cache]
---
During the performance optimizations, it was noticed that the determination of the initial state, for a state machine, is currently associated with a quite high database load, although only one ID must be determined. This has the consequence that during the checkout unnecessarily much load on the database is caused, in order to determine the initial state id, because this must be determined for the `order.state`, `order_delivery.state` as well as the `order_transaction.state` machine. Responsible for the load were the `\Shopware\Core\System\StateMachine\StateMachineRegistry::getInitialState` method, which is usually used as follows:

```php
$this->stateMachineRegistry->getInitialState(OrderStates::STATE_MACHINE, $context->getContext())->getId(),
//...
$this->stateMachineRegistry->getInitialState(OrderDeliveryStates::STATE_MACHINE, $context->getContext())->getId(),
//...
$this->stateMachineRegistry->getInitialState(OrderTransactionStates::STATE_MACHINE, $context->getContext())->getId(),
//...
```

Inside the `getInitialState`, the complete `StateMachine` object is loaded, including all `transitions` and their `from` and `to` states:

```
$criteria = new Criteria();
$criteria
    ->addFilter(new EqualsFilter('state_machine.technicalName', $name))
    ->setLimit(1);

$criteria->getAssociation('transitions')
    ->addSorting(new FieldSorting('state_machine_transition.actionName'))
    ->addAssociation('fromStateMachineState')
    ->addAssociation('toStateMachineState');

$criteria->getAssociation('states')
    ->addSorting(new FieldSorting('state_machine_state.technicalName'));
```

Since this means unnecessary load for the database, we have `@deprecated` this method for `v6.5.0.0` and provided a new smaller and faster service. Furthermore, all usages in the core have been removed and replaced with the new service:

```php
<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Loader;

class InitialStateIdLoader implements ResetInterface
{
    public const CACHE_KEY = 'state-machine-initial-state-ids';

    public function get(string $name): string
    {
        if (isset($this->ids[$name])) {
            return $this->ids[$name];
        }

        $this->ids = $this->load();

        return $this->ids[$name];
    }

    private function load(): array
    {
        return $this->cache->get(self::CACHE_KEY, function () {
            return $this->connection->fetchAllKeyValue(
                'SELECT technical_name, LOWER(HEX(`initial_state_id`)) as initial_state_id FROM state_machine'
            );
        });
    }
}
```

With the help of this service we were able to reduce the determination of the initial state under load by a multiple. The cache shown here is invalidated by a DAL written event on the state_machine entity. 
