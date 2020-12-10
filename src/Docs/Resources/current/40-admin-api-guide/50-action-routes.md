[titleEn]: <>(Action Routes)
[hash]: <>(article:admin_api_action)

Some changes to entities need some business logic to be done correctly. The direct change of the entities is not enough for these.
For this purpose we implemented action routes. These routes are not directly associated with an entity and differ for each case.

#### State Transitions
The states of a state machine should not be changed directly on an entity if there are dependencies, which mostly is the case. 
Because of this the state machine transitions for Order, Order_Transaction and Order_Delivery have corresponding action routes.

##### Order State Transition
The action route for the order state checks if the transition is allowed due to the state machine definitions and writes the state machine history entries.
It also takes care of sending the defined state change mails. It takes arrays of mediaIds and/or documentIds to add them as attachments to the mails.
The `transition` parameter can be one of the transitions defined in `\Core\Checkout\Order\OrderStates`. 

```
POST /api/_action/order/{orderId}/state/{transition}
{
    "mediaIds" : [
        "b7d2554b0ce847cd82f3ac9bd1c0dfca",
        "c8b2784a2ab9e8ce9343ac15e1c06edb"
    ],
    "documentIds": [
        "c8e3665c1df958de9304bdace2d1e0db",
        "d9c3895b3bcaf9dfa454bd26f2d17fec"
    ]    
    
}
```

##### Order Delivery State Transition
The action route for the order delivery state checks if the transition is allowed due to the state machine definitions and writes the state machine history entries.
It also takes care of sending the defined state change mails. It takes arrays of mediaIds and/or documentIds to add them as attachments to the mails.
The `transition` parameter can be one of the transitions defined in `\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates`.

```
POST /api/_action/order_delivery/{orderDeliveryId}/state/{transition}
{
    "mediaIds" : [
        "b7d2554b0ce847cd82f3ac9bd1c0dfca",
        "c8b2784a2ab9e8ce9343ac15e1c06edb"
    ],
    "documentIds": [
        "c8e3665c1df958de9304bdace2d1e0db",
        "d9c3895b3bcaf9dfa454bd26f2d17fec"
    ]    
    
}
```

##### Order Transaction State Transition
The action route for the order transaction state checks if the transition is allowed due to the state machine definitions and writes the state machine history entries.
It also takes care of sending the defined state change mails. It takes arrays of mediaIds and/or documentIds to add them as attachments to the mails.
The `transition` parameter can be one of the transitions defined in `\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates`.

```
POST /api/_action/order_transaction/{orderTransactionId}/state/{transition}
{
    "mediaIds" : [
        "b7d2554b0ce847cd82f3ac9bd1c0dfca",
        "c8b2784a2ab9e8ce9343ac15e1c06edb"
    ],
    "documentIds": [
        "c8e3665c1df958de9304bdace2d1e0db",
        "d9c3895b3bcaf9dfa454bd26f2d17fec"
    ]    
    
}
```
