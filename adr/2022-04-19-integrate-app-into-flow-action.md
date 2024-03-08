---
title: Integrate an app into flow action
date: 2022-04-19
area: services-settings
tags: [flow, app, flow-action]
---

We want to offer apps the possibility to deliver their own flow actions. Each app should be able to deliver multiple flow actions. The implementation should be done via webhooks and XML configuration. The information will be stored in the database as usual. If the app is uninstalled, all data will be deleted.

## Webhooks
Actions within a flow are currently realized via the event system. If the user has configured certain flow actions for a flow trigger (a business event within Shopware, e.g. `CheckoutOrderPlaced`), an event is triggered in the background for each configured action. Each action defines a listener for this event, which then executes the corresponding logic. However, since apps cannot include PHP code, we want to give apps the ability to automatically call a configurable webhook in the background.

To identify these flow actions, we store an `app_id` at each `flow_sequence` record in the database. In the `FlowExecutor`, we can thus identify that it is a flow action and automatically call the corresponding webhook for that app.

## Configuration
For flow actions, configuration parameters may be necessary that can be stored individually for each action. This should also be possible for apps. The flow actions are configured in a new `Resources/flow-action.xml` file. The following information can be stored for a flow action:
1) `<meta>` - Meta information for identification and UI.
2) `<headers>` - header information for the webhook
3) `<parameters>` - parameter information for the webhook
4) `<config>` - configuration information for the admin UI

A complete XML structure looks like this:
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
