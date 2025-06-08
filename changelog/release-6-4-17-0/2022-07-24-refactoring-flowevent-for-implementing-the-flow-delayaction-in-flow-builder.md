---
title: Refactoring FlowEvent for implementing the flow DelayAction in Flow Builder
issue: NEXT-22263
---
# Core
* Added `StorableFlow` class in `Shopware\Core\Content\Flow\Dispatching` to implement the flow DelayAction in FlowBuilder.
* Changed the `dispatch`, `callFlowExecutor` methods in `Shopware\Core\Content\Flow\Dispatching\FlowDispatcher`, use the `StorableFlow` instead of the original events.
* Changed the `execute`, `executeSequence`, `executeIf`, `executeAction`, `executeSequence` methods in `Shopware\Core\Content\Flow\Dispatching\FlowExecutor`, use the `StorableFlow` instead of `FlowState` or `FlowEventAware`.
* Added new `FlowFactory` class in `Shopware\Core\Content\Flow\Dispatching` to create and restore the `StorableFlow`.
* Added new awareness interfaces:
  `Shopware\Core\Content\Flow\Dispatching\Aware\ConfirmUrlAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\ContactFormDataAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\ContentsAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\ContextTokenAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\CustomerRecoveryAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\DataAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\EmailAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\MessageAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\NameAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\NewsletterRecipientAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\OrderTransactionAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\RecipientsAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\ResetUrlAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\ShopNameAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\SubjectAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\TemplateDataAware`.
  `Shopware\Core\Content\Flow\Dispatching\Aware\UrlAware`.
* Added new classes storer to store the representation of available data and restore the available data for `StorableFlow` from the original events in  `Shopware\Core\Content\Flow\Dispatching\Storer`:
  `Shopware\Core\Content\Flow\Dispatching\Storer\ConfirmUrlStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\ContactFormDataStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\ContentsStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\ContextTokenStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\CustomerGroupStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\CustomerRecoveryStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\CustomerStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\DataStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\EmailStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\MessageStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\NameStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\NewsletterRecipientStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\OrderStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\OrderTransactionStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\RecipientsStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\ResetUrlStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\ShopNameStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\SubjectStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\TemplateDataStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\UrlStorer`.
  `Shopware\Core\Content\Flow\Dispatching\Storer\UserStorer`.
* Added index-key for the flow actions services tags.
* Changed all the flow builder actions in `Shopware\Core\Content\Flow\Dispatching\Action` from event subscriber to tagged services.
* Deprecated the `handle` functions in all the flow builder actions in `Shopware\Core\Content\Flow\Dispatching\Action`, use the function `handleFlow` instead.

___
# Next Major Version Changes
* In the next major, the flow actions are not executed over the symfony events anymore, we'll remove the dependence from `EventSubscriberInterface` in `Shopware\Core\Content\Flow\Dispatching\Action\FlowAction`.
that means, all the flow actions extends from `FlowAction` are become the services tag. 
* The flow builder will execute the actions via call directly the `handleFlow` function instead `dispatch` an action event.
* To get an action service in flow builder, we need define the tag action service with a unique key, that key should be an action name.
* About the data we'll use in the flow actions, the data will be store in the `StorableFlow $flow`, use `$flow->getStore('order_id')` or `$flow->getData('order')` instead of `$flowEvent->getOrder`.
  * Use `$flow->getStore($key)` if you want to get the data from aware interfaces. E.g: `order_id` in `OrderAware`, `customer_id` from `CustomerAware` and so on.
  * Use `$flow->getData($key)` if you want to get the data from original events or additional data. E.g: `order`, `customer`, `contactFormData` and so on.

**before**
```xml
 <service id="Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction">
    ...
    <tag name="flow.action"/>
</service>
```

```php
class FlowExecutor
{
    ...
    
    $this->dispatcher->dispatch($flowEvent, $actionname);
    
    ...
}

abstract class FlowAction implements EventSubscriberInterface
{
    ...
}

class SendMailAction extends FlowAction
{
    ...
    public static function getSubscribedEvents()
    {
        return ['action.name' => 'handle'];
    }
    
    public function handle(FlowEvent $event)
    {
        ...
        
        $orderId = $event->getOrderId();
        
        $contactFormData = $event->getConta();
        
        ...
    }
}
```

**after**
```xml
 <service id="Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction">
    ...
    <tag name="flow.action" key="action.mail.send" />
</service>
```

```php
class FlowExecutor
{
    ...
    
    $actionService = $actions[$actionName];
    
    $actionService->handleFlow($storableFlow);
    
    ...
}

abstract class FlowAction
{
    ...
}

class SendMailAction extends FlowAction
{
    ...
    // The `getSubscribedEvents` function has been removed.
    
    public function handleFlow(StorableFlow $flow)
    {
        ...
        
        $orderId = $flow->getStore('order_id');
        
        $contactFormData = $event->getData('contactFormData');
        
        ...
    }
}
```
