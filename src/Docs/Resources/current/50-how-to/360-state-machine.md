[titleEn]: <>(How to use states and state machines)
[metaDescriptionEn]: <>(Orders use the state machines to track states for deliveries and payments. Here is how you manage these properly and use them for your own entities)
[hash]: <>(article:how_to_use_states_and_state_machines)

## Overview

To keep track of delivery and payment states an order has different states for both cases. They are not simple foreign key to a state table. States are more complex as you can see in the [state machine ERD](../60-references-internals/10-core/10-erd/erd-shopware-core-system-statemachine.md).

This complexity adds useful features as well. The change of states are tracked over time and only transitions that make sense to do are available so it is more fail proof.

There is an [overview of state machine states and transitions](../60-references-internals/10-core/50-checkout-process/20-order.md) that shows how the order states are structured to make clear how the states are intended. You don't have to remember all the technical names. The technical names for the state machines and their states are available as constants in the `OrderStates`, `OrderDeliveryStates` and `OrderTransactionStates`. 

## State management of an entity

An entity can have more than a single state field and this often requires you to pass in the field name for the upcoming code snippets. When there is a need to store two or more states on an entity there is probably a need to separate the entity.


### How to make an entity stateful

The entity definitions needs to have a StateMachineStateField. The type of the field indicates it to be important for the state machine management but it behaves like a common foreign key.

```php
(new StateMachineStateField('state_id', 'stateId', $technicalNameOfStateMachine))->setFlags(new Required()),
new ManyToOneAssociationField('stateMachineState', 'state_id', StateMachineStateDefinition::class, 'id', true),
```

As it is a relation to a state machine state you need an id to the state table:

```sql
ALTER TABLE `foo_bar`
    ADD COLUMN `state_id` BINARY(16) NOT NULL
    ADD CONSTRAINT `fk.foo_bar.state_id`
        FOREIGN KEY (`state_id`)
        REFERENCES `state_machine_state` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
```


### How to get the current state of an entity

A state on an entity is a foreign key pointing to the current state. To read the current technical name of an entity state you can read it directly from the entity:

```php
/* @var $order \Shopware\Core\Checkout\Order\OrderEntity */
$order = $orderRepository->search(new Criteria([$id]))->first();
$order->getStateMachineState()->getTechnicalName();
```

### How to change the state of an entity

You can't set the state id to just change the state of an entity. To enforce the right usage of the field it is write protected. The StateManagerRegistry is the way to go for every change of an entitys state.

```php
/* @var $stateMachineRegistry \Shopware\Core\System\StateMachine\StateMachineRegistry */
$stateMachineRegistry->transition(new Transition(
    'foo_bar',
    $entityId,
    $transitionName,
    'stateId'
));
```

If the entity isn't able to be in this state by state machine definition the transition will fail. In the case where you don't know the exact steps to go for the state of your choice you can use the transition walker. That tries to reach your state by taking the shortest path of states.

```php
/* @var $stateMachineTransitionWalker \Shopware\Core\System\StateMachine\StateMachineTransitionWalker */
$stateMachineTransitionWalker->walkPath(
    'foo_bar',
    $entityId,
    'stateId',
    'anyStateOfChoice',
    $context
);
```

### How to get the next possible steps

When you want to show choices for user interactions of the next possible transactions you can push an entity into you can use the StateMachineRegistry as well:

```php
/* @var $stateMachineRegistry \Shopware\Core\System\StateMachine\StateMachineRegistry */
$stateMachineRegistry->getAvailableTransitions('foo_bar', $entityId, 'stateId', $context);
```

## Custom state machine

As you develop your own entity relying on a state machine state you also need a state machine that will follow your custom rules. In the upcoming sections you will see how to build the following state machine for a bottle entity and learn how to extend existing ones as the steps are identical:

![custom state machine](./dist/360-state-machine-custom-state-machine.png)


### How to create a custom state machine

The most reliable way to ship your state machine is by providing a migration that creates the state machine:

```php
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

$stateMachineId = Uuid::randomBytes();
$now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
/* @var $connection \Doctrine\DBAL\Connection */

$connection->insert('state_machine', [
    'technical_name' => 'foo_bar_machine',
    'id' => $stateMachineId,
    'created_at' => $now,
]);
$connection->insert('state_machine_translation', [
    'name' => 'Foobar states',
    'state_machine_id' => $stateMachineId,
    'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
    'created_at' => $now,
]);
```

### How to add a state to a state machine

As a follow up to the previous step you will see that an insert is everything you need to add a state machine state. Make sure to keep the primary key stored in a variable as they are needed for the next step as well.

```php
$emptyStateId = Uuid::randomBytes();
$fullStateId = Uuid::randomBytes();

$connection->insert('state_machine_state', [
    'id' => $emptyStateId,
    'state_machine_id' => $stateMachineId,
    'technical_name' => 'empty',
    'created_at' => $now
]);
$connection->insert('state_machine_state_translation', [
    'name' => 'Empty',
    'state_machine_state_id' => $emptyStateId,
    'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
    'created_at' => $now,
]);
$connection->insert('state_machine_state', [
    'id' => $fullStateId,
    'state_machine_id' => $stateMachineId,
    'technical_name' => 'full',
    'created_at' => $now
]);
$connection->insert('state_machine_state_translation', [
    'name' => 'Full',
    'state_machine_state_id' => $fullStateId,
    'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
    'created_at' => $now,
]);
```

Be aware that you should set an initial state for your state machine after you created your first states:

```php
$connection->update('state_machine', [
    'initial_state_id' => $emptyStateId,
    'updated_at' => $now,
], [
    'id' => $stateMachineId,
]);
```

### How to add a new transition between two states

After you add a state you have to make it available for your entities by adding at least one transition so the StateMachineRegistry can change the state of an entity to it. A transition is just a name for the change from one specific to an other specific state. So the inserts look similar to the previous ones and also depend on the previously inserted data:

```php
$connection->insert('state_machine_transition', [
    'action_name' => 'fill',
    'id' => Uuid::randomBytes(),
    'state_machine_id' => $stateMachineId,
    'from_state_id' => $emptyStateId,
    'to_state_id' => $fullStateId,
    'created_at' => $now
]);
$connection->insert('state_machine_transition', [
    'action_name' => 'drink',
    'id' => Uuid::randomBytes(),
    'state_machine_id' => $stateMachineId,
    'from_state_id' => $fullStateId,
    'to_state_id' => $emptyStateId,
    'created_at' => $now
]);
```
