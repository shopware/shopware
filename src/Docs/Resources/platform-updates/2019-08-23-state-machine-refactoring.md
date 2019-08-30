[titleEn]: <>(state machine refactoring)

The state machine has been refactored. Previously dedicated routes for every state machine 
were needed. To simplify the handling with state machine and ensure that you can not change
a state machine state without writing a history entry, a new field called
`StateMachineStateField` has been added.

Usage:
`new StateMachineStateField('state_id', 'stateId', 'stateMachineName')`

The `OrderActionController`, `OrderDeliveryActionController` and 
`OrderTransactionActionController` and their corresponding api services in the administration
have been removed. 

Instead a more generic `StateMachineActionController` has been added. You can now get the 
available transitions by using the route:
`GET /api/v1/_action/state-machine/{entityName}/{entityId}/state`
and change a state with:
`POST /api/v1/_action/state-machine/{entityName}/{entityId}/state/{transition?}`.
There is also an api service called `state-machine.api.service.js` in the administration.

