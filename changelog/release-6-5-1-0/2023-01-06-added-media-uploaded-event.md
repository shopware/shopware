---
title: added-media-uploaded-event
issue: NEXT-25027
author: Hung Mac
author_email: hung@shapeandshift.dev
author_github: hungmac-sw
---
# Core
* Added new event class `Shopware/Core/Content/Media/Event/MediaUploadedEvent.php`
* Added new event `media.uploaded` in `Shopware/Core/Content/Media/MediaEvents.php`
* Changed `Shopware/Core/Content/Media/Api/MediaUploadController::upload` to dispatch the new `MediaUploadedEvent` event after successfuly saving media to the database.
* Changed `\Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector` to make events of the `media` entity hookable.
* Added new classes to `Shopware\Core\Content\Flow\Dispatching`:
    * `Aware\MediaUploadedAware.php`
    * `Storer\MediaUploadedStorer.php`
