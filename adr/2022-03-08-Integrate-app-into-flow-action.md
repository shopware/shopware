# 2022-03-08 - Integrate an app into flow action

## Context
Currently, the actions in the flow builder are limited, it will be quite complicated if you want to add a new action and the user cannot add them by themselves.
Service providers need to be able to offer an App, which is able to allow shopware to interact with their services. 
Currently, an App is just listening to an event in shopware. With the Flow Builder arises the need to add actions to the Flow Builder.

## Decision
We integrate App system into Flow builder and use Webhook to call API

### Generate app flow actions and webhooks when install a new app system
App system can define one or more actions in the manifest xml file.
The actions defined in manifest.xml are synchronized into the `app_flow_action` table. The actions stored there are used for the UI in the admin to indicate to the user which actions
can configure. When an app action is configured in a flow, we store the associated `app_flow_action_id` on the `flow_sequence` for later identification.

Each concurrently created action will register a webhook to be called on a predefined URL if some events (that using an App flow action) happen inside Shopware.

### Load actions that created by App system when use Flow Builder
In Administration, when list all actions that can be used in Flow Builder, in addition to the actions in Core, we also load the actions that have been installed by the App system.
In the `flow_sequence` table, we have the `app_flow_action_id` column to know which record is the App flow action.

### Dispatch an AppFlowActionEvent when an app flow action is executed in FlowExecutor
At FlowExecutor, when we execute an action that is app flow action (based on `app_flow_action_id` column in flow_sequence) we will dispatch `AppFlowActionEvent` instead of `FlowEvent`.
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

### Generate headers and payload for webhook in AppFlowActionProvider
`headers` and `payload` are saved as JSON with variables, we have to replace variables by data that available in event and data config in flow.
For example, in manifest file we have a parameter:

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

Before parsing `message` parameter to `payload` of `AppFlowActionEvent`, we need resolve all variables on `value` of that parameter.
All logic to get data from AppFlowAction and generate Payload data and Headers data is handled in `AppFlowActionProvider`.
The variables in the Payload and Headers will be converted to values, that's taken from the action's config data and the event's availableData.

```php
private function resolveParamsData(array $params, array $data, Context $context): array
    {
        $paramData = [];

        foreach ($params as $key => $param) {
            try {
                $paramData[$key] = $this->templateRenderer->render($param, $data, $context);
            } catch (\Throwable $e) {
                $this->logger->error(
                    "Could not render template with error message:\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code:' . $e->getCode() . "\n"
                    . 'Template source:'
                    . $param['value'] . "\n"
                    . "Template data: \n"
                    . \json_encode($data) . "\n"
                );

                $paramData[$key] = null;
            }
        }

        return $paramData;
    }
```

### Call webhook in WebhookDispatcher when dispatching AppFlowActionEvent
Workflow of `WebhookDispatcher` is basically unchanged.
After the `AppFlowActionEvent` is dispatched, the `WebhookDispatcher` will check if the webhook of this event has been generated in the `webhook` table. Only events registered in the `webhook` table with actived app system, will be processed.
`WebhookDispatcher` gets the `payload` from the event and generate default `headers`. If the event is a `AppFlowActionEvent`, the `headers` will also be got from the event.

`WebhookDispatcher` will generate `Request` based on `url` get from `webhook` table, `payload` and `headers` from the event, and default method is `POST` for all webhooks.
Finally, `WebhookDispatcher` sends `Request` by `Guzzle`.

## Consequences
We can make Action for Flow builder easy to call 3rd party APIs by configuring manifest file for an App System. The way it's displayed in the action form is also easily customizable.
The logic from FlowBuilder and WebhookDispatcher has not changed much.
