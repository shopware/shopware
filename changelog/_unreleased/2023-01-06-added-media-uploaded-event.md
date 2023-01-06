---
title: added-media-uploaded-event
issue: NEXT-
author: Hung Mac
author_email: hung@shapeandshift.dev
author_github: hungmac-sw
---
# Core
* Created a new event name `media.uploaded` in `Shopware/Core/Content/Media/Event/MediaUploadedEvent`, the webhook will be sending this event to the apps.
* Added `MediaUploadedEvent` event dispatch to `Shopware/Core/Content/Media/Api/MediaUploadController::upload`, After the media is saved to the system successfully, it will be sending a webhook to the apps.
* Changed `\Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector` to make the written events of the `media` entity hookable.
* Added new awareness interface: `Shopware\Core\Content\Flow\Dispatching\Aware\MediaUploadedAware`.
* Added new classes storer: `Shopware\Core\Content\Flow\Dispatching\Storer\MediaUploadedStorer`.
