---
title: Integrate an app into the flow event
date: 2022-10-11
area: services-settings
tags: [flow, app]
---

# 2022-10-11 - Integrate an app into the flow event

## Context
Currently, apps can not extend the list of available events in the flow builder.

## Decision
We update the flow builder so the apps can expand the list of available trigger events in the flow builder UI.

### Flow aware
We define flow aware classes to detect which data will be available in the event and a function to get them.

**Problems:**
* We are unsure which data will the app event provide

**Solution:**
* We create an interface CustomAppAware that will use as implementation for the custom event from the app.

**Example pseudocode**
```php
interface CustomAppAware
{
    public const APP_DATA = 'customAppData';

    public function getCustomAppData(): array;
}
```

### Flow storer
Flow data storer saves the data from the event as the [StorableFlow](../adr/2022-07-21-adding-the-storable-flow-to-implement-delay-action-in-flow-builder.md), and we use them in flow actions.

**Problems:**
* Currently, we keep the event data in the core but do not store any personalized event data from the application.

**Solution:**
* We create a CustomAppStorer, which is used to store the data from custom app event.
* When the API triggers a custom trigger, the data in the body will be stored in FlowStore by their keys.

*Example to define data from the API:*
```json
    {
        "customerId": "d20e4d60e35e4afdb795c767eee08fec",
        "salesChannelId": "55cb094fd1794d489c63975a6b4b5b90",
        "shopName": "Shopware's Shop",
        "url": "https://shopware.com" 
    }
```

*After that, at actions we can get data thought FlowStorer.*
```php
    $salesChanelId = $flow->getData(MailAware::SALES_CHANNEL_ID));
    $customer = $flow->getData(CustomerAware::CUSTOMER_ID));
```

*Or we can use the data when defining the email template.*
```html
    <h3>Welcome to {{ shopName }}</h3>
    <h1>Visit us at: {{ url }} </h1>
```

**Example pseudocode**
```php
class CustomAppStore extends FlowStorer
{
    public function store(FlowEventAware $event, array $stored): array
    {
        //check if $event is an instance of CustomAppAware
        foreach ($event->getCustomAppData() as $key => $data) {
            $stored[ScalarValuesAware::STORE_VALUES][$key] = $data;
            $stored[$key] = $data;
        }
    }

    public function restore(StorableFlow $storable): void
    {
        return;
    }
}
```

### Flow Events
Events must implement FlowEventAware to be able to available in the flow builder triggers.

**Problems:**
* We do not possess any `FlowEventAware` event instances that app developers can utilize for custom triggers to be dispatched or triggered from an app.

**Solution:**
* We create a new CustomAppEvent class that can be triggered by the App system.

**Example pseudocode**
```php
class CustomAppEvent extends Event implements CustomAppAware, FlowEventAware
{
    private string $name;

    private array $data;
    
    // __construct()
    //getters
}
```

### BusinessEventCollector
BusinessEventCollector collects events that implemented FlowEventAware and output to flow builder.

**Problems:**
* We currently collect events that implemented FlowEventAware. So the collector does not contain the events from the activated app.

**Solution:**
* We will collect all `CustomAppEvent` events from activated apps.

**Example pseudocode**
```php
public function collect(Context $context): BusinessEventCollectorResponse
{
    //fetch app event
    $this->fetchAppEvents(new BusinessEventCollectorResponse)
}

private function fetchAppEvents(BusinessEventCollectorResponse $result): BusinessEventCollectorResponse
{
    //check valid app events from the database
    return $this->createCustomAppEvent();
}

private function createCustomAppEvent(): CustomAppEvent
{
   // return new CustomAppEvent
}
```

### Trigger app custom events API
We will provide an APIs to trigger CustomAppEvent.

**Problems:**
* Currently, the events are provided and triggered from the core when the user performs specific actions from the storefront or admin, like checkout order or user recovery. 3rd parties can not add custom triggers and trigger them by themself.

**Solution:**
* We will provide an API. The app calls the API to trigger the custom event and needs to provide the event name and the data. The API will create a CustomAppEvent object and dispatch it with the information provided.

**Example pseudocode**
```php
    /**
     * @Since("6.5.2.0")
     */
    #[Route(path: '/api/_action/trigger-event/{eventName}', name: 'api.action.trigger_event', methods: ['POST'])]
    public function flowCustomTrigger(string $eventName, Request $request, Context $context): JsonResponse
    {
        $data = $request->request->all();
        
        $criteria = new Criteria([$data['flowAppEventId']])
        $criteria->addFilter(new EqualsFilter('appId', $data['flowId']));
        $criteria->addFilter(new EqualsFilter('app.active', 1));

        $flowEvent = $flowAppEventRepository->search($criteria);
        //return http status code 404 if $flowEvent is empty
        
        $this->eventDispatcher->dispatch(new CustomAppEvent($flowEvent->getName(), $data));
        //return http status code 200 and success message
    }

```

## Defining an App flow event in Xml
The flow events are configured in a `<appRoot>/src/Resources/flow.xml` file. We can store the following information for a flow event, Also, we can define more than one event in one app:

1. `<name>` - The technical name - is unique and should be prefixed with the app vendor prefix, used when dispatching CustomAppEvent.php.
2. `<aware>` - Use for deciding what flow actions will be allowed to show after the event.

    - The list of aware supported following:
        - `orderAware`
        - `customerAware`
        - `mailAware`
        - `userAware`
        - `salesChannelAware`
        - `productAware`
        - `customerGroupAware`
      
    - _Example:_

   _`<aware>orderAware</aware>`_

   _We will have a list of actions related to Order that can be selected at the flow below:_

    - action.add.order.tag,
    - action.remove.order.tag,
    - action.generate.document,
    - action.grant.download.access,
    - action.set.order.state,
    - action.add.order.affiliate.and.campaign.code,
    - action.set.order.custom.field,
    - action.stop.flow

   _`<aware>customerAware</aware>`_

   _We will have a list of actions related to Customer that can be selected at the flow below:_

    - action.add.customer.tag
    - action.remove.customer.tag
    - action.change.customer.group
    - action.change.customer.status
    - action.set.customer.custom.field
    - action.add.customer.affiliate.and.campaign.code
    - action.stop.flow

A complete XML structure looks like this:
```xml
<flow-extensions xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://test-flow.com/flow-1.0.xsd">
    <flow-events>
        <flow-event>
            <name>swag.before.open.the.doors</name>
            <aware>customerAware</aware>
            <aware>orderAware</aware>
        </flow-event>
        <flow-event>
            ...
        </flow-event>
    </flow-events>
</flow-extensions>
```

## Defining translated
We support defining translation for custom trigger events to show in the trigger tree and the trigger's name in the flow list.

* We will create the snippet file in folder `<appRoot>/src/Resources/app/administration/snippet/`. The structure of the snippet should follow some principles below:
  * `sw-flow-custom-event` is a fixed key instance for snippets using at the trigger event.
  * `event-tree` is a fixed key. The keys are defined inside this key based on the specified trigger name at `name` in `flow.xml` used to translate in trigger tree.
  * `flow-list` is a fixed key, The keys defined inside the key based on the trigger name defined at `name` in `flow.xml` used to translate in the trigger tree.
    **Example pseudocode**
    ```json
    {
    "sw-flow-custom-event": {
      "event-tree": {
        "swag": "Swag",
        "before": "Before",
        "openTheDoors": "Open the doors"
      },
      "flow-list": {
        "swag_before_open_the_doors": "Before open the doors"
      }
    }
}
```

## Database migration
* We will create a new table `app_flow_event` to save defined data from the `<appRoot>/src/Resources/flow.xml` file.
* The table will have columns like bellow:
  * `id` BINARY(16) NOT NULL,
  * `app_id` BINARY(16) NOT NULL,
  * `name` VARCHAR(255) NOT NULL UNIQUE,
  * `aware` JSON NOT NULL,
  * `created_at` DATETIME(3) NOT NULL,
  * `updated_at` DATETIME(3) NULL,
