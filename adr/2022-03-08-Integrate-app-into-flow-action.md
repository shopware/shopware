# 2022-03-08 - Integrate an app into flow action

## Context
Currently, the actions in the flow builder are limited, it will be quite complicated if you want to add a new action and the user cannot add them by themselves.
Service providers need to be able to offer an App, which is able to allow shopware to interact with their services. 
Currently, an App is just listening to an event in shopware. With the Flow Builder arises the need to add actions to the Flow Builder.

## Decision
We integrate App system into Flow builder and use Webhook to call API

### Generate app flow actions and webhooks when install a new app system
App system can define one or more actions in the manifest xml file.
By adding the necessary attributes, the user can define how the action should be displayed as well as how the information should be sent to the 3rd party.

```xml
<flow-actions xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://test-flow.com/flow-action-1.0.xsd">
    <flow-action>
        <meta>
            <name>telegram.send.message</name>
            <badge>Telegram</badge>
            <label>Telegram send message</label>
            <description>Telegram send message description</description>
            <url>https://test-flow.com</url>
            <sw-icon>default-communication-speech-bubbles</sw-icon>
            <requirements>orderAware</requirements>
        </meta>
        <headers>
            <parameter type="string" name="content-type" value="application/json"/>
        </headers>
        <parameters>
            <parameter type="string" name="message" value="{{ subject }} \n {{ customer.lastName }} some text here"/>
        </parameters>
        <config>
            <input-field type="text">
                <name>subject</name>
                <label>Subject</label>
                <required>true</required>
            </input-field>
        </config>
    </flow-action>
</flow-actions>
```

The actions defined in manifest.xml are stored into the `app_flow_action` table. The actions stored there are used for the UI in the admin to indicate to the user which actions
can configure. When an app action is configured in a flow, we store the associated `app_flow_action_id` on the `flow_sequence` for later identification.

Each concurrently created action will register a webhook to be called on a predefined URL if some events (that using an App flow action) happen inside Shopware.

### Load actions that created by app system in flow builder
In administration, when list all actions that can be used in flow builder, in addition to the actions in core, we also load the actions that have been installed by the app system.
In the `flow_sequence` table, we have the `app_flow_action_id` column to know which record is the app flow action.

### Dispatch an AppFlowActionEvent when an app flow action is executed in FlowExecutor
For each action, the `FlowExecutor` dispatches a `FlowEvent`, which will be then handled by the subscriber or listener defined in core.
With app flow action, all events after being dispatched by `FlowExecutor` will be handled by `WebhookDispatcher`. To call the webhook in `WebhookDispatcher` we need some information like payload, headers.. from the event, and it will make the event more complicated if we put all these properties into the `FlowEvent`. Meanwhile, the other properties of `FlowEvent` are not currently used in `WebhookDispatcher`.
That's why we need to define `AppFlowActionEvent` and it implements from `Hookable`, `FlowExecutor` will dispatch this event instead of `FlowEvent` when handling actions from app system (based on `app_flow_action_id` column in flow_sequence).

We use `AppFlowActionProvider` to get event data (`headers` and `payload`) which will be use in `WebhookDispatcher`.

```php
   if ($sequence->appFlowActionId) {
       $eventData = $this->appFlowActionProvider->getWebhookData($globalEvent, $sequence->appFlowActionId);

       $globalEvent = new AppFlowActionEvent(
           $actionName,
           $eventData['headers'],
           $eventData['payload']
       );
   }
```

### Data for the webhooks will be retrieved during the flow execution from AppFlowActionProvider
`headers` and `payload` are saved as JSON with variables, we have to replace variables with data before calling webhook.
We define values in config for this purpose. But some necessary data may not can be created in config.
For example, the user needs when a customer has successfully logged in, the system will send a message to the channel with the name of the logged in person. User cannot define the name of that customer in the config, instead the system will automatically get that customer's data in event's availableData.

```xml
<parameters>
    <parameter type="string" name="message" value="{{ subject }} \n {{ customer.lastName }} some text here"/>
</parameters>
<config>
    <input-field type="text">
        <name>subject</name>
        <label>Subject</label>
        <required>true</required>
    </input-field>
</config>
```

All logic to get data from AppFlowAction and generate `payload` data and `headers` data is handled in `AppFlowActionProvider`.
The variables in the Payload and Headers will be converted to values, that's taken from the action's config data and the event's availableData.

### Call webhook in WebhookDispatcher when dispatching AppFlowActionEvent
After the `AppFlowActionEvent` is dispatched, the `WebhookDispatcher` will check if the webhook of this event has been generated in the `webhook` table. Only events registered in the `webhook` table with actived app system, will be processed.
`WebhookDispatcher` gets the `payload` from the event and generate default `headers`. If the event is a `AppFlowActionEvent`, the `headers` will also be got from the event.

`WebhookDispatcher` will generate `Request` based on `url` get from `webhook` table, `payload` and `headers` from the event, and default method is `POST` for all webhooks.
Finally, `WebhookDispatcher` sends request by `Guzzle`.

## Consequences
We can make Action for Flow builder easy to call 3rd party APIs by configuring manifest file for an App System. The way it's displayed in the action form is also easily customizable.
The logic from FlowBuilder and WebhookDispatcher has not changed much.
