---
title: Improve delete unused media command
issue: NEXT-9496
---
# Core
* Deprecated `\Shopware\Core\Content\Media\DeleteNotUsedMediaService` service in favor of a new service which has an API more suited to parallel execution and more consideration for memory consumption.
* Added `\Shopware\Core\Content\Media\UnusedMediaPurger` service which allows to delete unused media in batches.
* Added `\Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent` which is dispatched when unused media is about to be deleted, giving the opportunity for listeners to mark some media as used to prevent them from being deleted.
* Changed `DeleteNotUsedMediaCommand` command so that it now uses the new service and provides options to control the batch size. A `--dry-run` option is also introduced which shows an interactive paginated list of all the unused media which will be deleted.
* Added a `--grace-period-days` option to specify the number of days to wait before deleting new unused media. The default is 20. Any media uploaded in the previous 20 days that is not used will not be deleted.
* Deprecated the `association_fields` column `Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderDefinition` definition as associations are now inferred from the `MediaDefinition`.
* Deprecated the methods `getAssociationFields`, `setAssociationFields` & the property `$associationFields` in `Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderEntity` as associations are now inferred from the `MediaDefinition`.
* Added `Shopware\Core\Content\Cms\Subscriber\UnusedMediaSubscriber` which prevents media's referenced in CMS pages from being removed.
* Added `Shopware\Core\Content\Media\Subscriber\CustomFieldsUnusedMediaSubscriber` which prevents media's referenced in custom fields from being removed.
* Added `Shopware\Storefront\Theme\Subscriber\UnusedMediaSubscriber` which prevents media's referenced in themes from being removed.
___
# Next Major Version Changes
## Removed `\Shopware\Core\Content\Media\DeleteNotUsedMediaService`
All usages of `\Shopware\Core\Content\Media\DeleteNotUsedMediaService` should be replaced with `\Shopware\Core\Content\Media\UnusedMediaPurger`. There is no replacement for the `countNotUsedMedia` method because counting the number of unused media on a system with a lot of media is time intensive.
The `deleteNotUsedMedia` method exists on the new service but has a different signature. `Context` is no longer required. To delete only entities of a certain type it was previously necessary to add an extension to the `Context` object. Instead, pass the entity name to the third parameter of `deleteNotUsedMedia`.
The first two parameters allow to process a slice of media, passing null to those parameters instructs the method to check all media, in batches.
___
# Upgrade Information
## Marking media as used 
If your plugin references media in a way that is not understood by the DAL, for example in JSON blobs, it is now possible for your plugin to inform the system that this media is used and should not be deleted when the `\Shopware\Core\Content\Media\UnusedMediaPurger` service is executed.
To do this, you need to create a listener for the `Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent` event. This event can be called multiple times during the cleanup task with different sets of media ID's scheduled to be deleted. Your listener should check if any of the media ID's passed to the event are used by your plugin and mark them as used by calling the `markMediaAsUsed` method on the event object with an array of the used media ID's.
You can get the media ID's scheduled for deletion from the event object by calling the `getMediaIds` method.

See the following implementations for an example: 
* \Shopware\Core\Content\Cms\Subscriber\UnusedMediaSubscriber
* \Shopware\Storefront\Theme\Subscriber\UnusedMediaSubscriber
