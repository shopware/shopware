---
title: Create DelayableAction interface to able actions delayed
issue: NEXT-23533
---
# Core
* Added the `DelayableAction` interface in `Shopware\Core\Content\Flow\Dispatching`.
* Changed all the flow actions in `Shopware\Core\Content\Flow\Dispatching\Action`, implemented the `DelayableAction` interface to able delayed.
* Added more field `delayable` in `app_flow_action` table, the app flow actions can be delayed if defined the `delayable` in `xml` file when define the app flow actions.
* Added more protected `delayable` in `Shopware\Core\Framework\App\FlowAction\Xml\Metadata`.
* Changed the `toArray` function in `Shopware\Core\Framework\App\FlowAction\Xml\Action` to get more delayable field.
* Changed the `fetchAppActions`, `define` functions in `Shopware\Core\Content\Flow\Api\FlowActionDefinition` to add more `delayable` value.
