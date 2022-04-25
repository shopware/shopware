---
title: Delete all flow actions before update the app
issue: NEXT-17540
---
# Core
* Changed the `updateActions` function in `Shopware\Core\Framework\App\Lifecycle\Persister\FlowActionPersister` to make sure not affect to already configured flows when update the app. 
