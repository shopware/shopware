---
title: Internal repositories
issue: NEXT-21456
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---
# Core
* Deprecated `MediaRepositoryDecorator`, the class will be removed with next major. If you typed hint this class, replace it with `EntityRepositoryInterface`
* Deprecated `MediaThumbnailRepositoryDecorator`, the class will be removed with next major. If you typed hint this class, replace it with `EntityRepositoryInterface`
* Deprecated `MediaFolderRepositoryDecorator.php`, the class will be removed with next major. If you typed hint this class, replace it with `EntityRepositoryInterface`
* Deprecated  `PaymentMethodRepositoryDecorator`, the class will be removed with next major.
* Deprecated `MediaThumbnailRepositoryDecorator::delete` flat ids support. You have to map the ids now like other repositories
* Deprecated `EntityRepositoryInterface`, the class will be removed with next major, type hint with `EntityRepository` instead
* Deprecated `SalesChannelRepositoryInterface`, the class will be removed with next major, type hint with `SalesChannelRepository` instead
* Added `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent::$cloned` property, to identify written events which are triggered over the clone function
* Added `\Shopware\Core\Content\Media\DataAbstractionLayer\MediaDeletionSubscriber` to handle private media read protections and synchronize entity and filesystem deletions
___
# Upgrade Information
## Removed repository decorators
The following repository decorator classes will be removed with the next major:
* `MediaRepositoryDecorator`
* `MediaThumbnailRepositoryDecorator`
* `MediaFolderRepositoryDecorator`
* `PaymentMethodRepositoryDecorator`

If you use one of the repository and type hint against this specific classes, you have to change you type hints to `EntityRepository`:

### Before
```php
private MediaRepositoryDecorator $mediaRepository;
```

### After
```php
private EntityRepositoryInterface $mediaRepository;
```

## Thumbnail repository flat ids delete
The `media_thumbnail.repository` had an own implementation of the `EntityRepository`(`MediaThumbnailRepositoryDecorator`) which breaks the nested primary key pattern for the `delete` call and allows providing flat id arrays. If you use the repository in this way, you have to change the usage as follow:

### Before
```php
$repository->delete([$id1, $id2], $context);
```

### After
```php
$repository->delete([
    ['id' => $id1], 
    ['id' => $id2]
], $context);
```

## `@internal` entity repositories
We marked the `EntityRepositoryInterface` & `SalesChannelRepositoryInterface` classes as `@deprecated` and will be removed and the `EntityRepository` & `SalesChannelRepository` as final, to be able to release future optimizations more easily. Therefor if you implement an own repository class for your entities, you have to remove this. To modify the repository calls you can use one of the following events:
* `BeforeDeleteEvent`: Allows an access point for before and after deleting the entity
* `EntitySearchedEvent`: Allows access points to the criteria for search and search-ids
* `PreWriteValidationEvent`/`PostWriteValidationEvent`: Allows access points before and after the entity written
* `SalesChannelProcessCriteriaEvent`: Allows access to the criteria before the entity is search within a sales channel scope

Additionally, you have to change your type hints from `EntityRepositoryInterface` to `EntityRepository` or `SalesChannelRepository`:
___
# Next Major Version Changes
## Removed repository decorators
Removed the following repository decorators:
* `MediaRepositoryDecorator`
* `MediaThumbnailRepositoryDecorator`
* `MediaFolderRepositoryDecorator`
* `PaymentMethodRepositoryDecorator`

If you used one of the repository and type hint against this specific classes, you have to change your type hints to `EntityRepository`:

### Before
```php
private MediaRepositoryDecorator $mediaRepository;
```

### After
```php
private EntityRepositoryInterface $mediaRepository;
```

## Thumbnail repository flat ids delete
The `media_thumbnail.repository` had an own implementation of the `EntityRepository`(`MediaThumbnailRepositoryDecorator`) which breaks the nested primary key pattern for the `delete` call and allowed you providing a flat id array. If you used the repository in this way, you have to change the usage as follows:

### Before
```php
$repository->delete([$id1, $id2], $context);
```

### After
```php
$repository->delete([
    ['id' => $id1], 
    ['id' => $id2]
], $context);
```

## `@internal` entity repositories
We removed the `EntityRepositoryInterface` & `SalesChannelRepositoryInterface` classes and declared the `EntityRepository` & `SalesChannelRepository` as final. Therefor if you implemented an own repository class for your entities, you have to remove this now. To modify the repository calls you can use one of the following events:
* `BeforeDeleteEvent`: Allows an access point for before and after deleting the entity
* `EntitySearchedEvent`: Allows access points to the criteria for search and search-ids
* `PreWriteValidationEvent`/`PostWriteValidationEvent`: Allows access points before and after the entity written
* `SalesChannelProcessCriteriaEvent`: Allows access to the criteria before the entity is search within a sales channel scope

Additionally, you have to change your type hints from `EntityRepositoryInterface` & `SalesChannelRepositoryInterface` to `EntityRepository` or `SalesChannelRepository`: