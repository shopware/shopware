---
title: Log theme compile errors after update
issue: NEXT-23975
---
# Core
* Changed `\Shopware\Core\Framework\Update\Api\UpdateController::finish` to create a notification after update.
* Added methods `getPostUpdateMessage` and `appendPostUpdateMessage` in `\Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent` to provide post update messages.
___
# Storefront
* Changed `Storefront\Theme\Subscriber\UpdateSubscriber::updateFinished` to not fail on theme recompile after update.
