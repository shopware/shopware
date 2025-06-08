---
title: Update App lifecycle element for flow custom event
issue: NEXT-21816
---
# Core
* Added `FlowEventPersister` class in `Shopware\Core\Framework\App\Lifecycle\Persister` for saving data config from `flow.xml` to `app_flow_event` table.
* Added `getFlowEvents` function in `Shopware\Core\Framework\App\Lifecycle\AppLoader` for getting data config from `flow.xml`.
* Changed `updateApp` function in `Shopware\Core\Framework\App\Lifecycle\AppLifecycle` for calling service `FlowEventPersister`.
