---
title: Validate media path and file name writes
---

# API

* Changed Admin API and Sync API writes to reject a relative `media.path` or `media_thumbnail.path` and a `null` path on update with `400` and `CONTENT__MEDIA_ILLEGAL_PATH`. An absolute `http` or `https` URL with a host and without dot segments, backslashes or `%2e` is still accepted. Writing back the unchanged stored value is still accepted.
* Changed Admin API and Sync API writes to validate `media.fileName` like the file name of an upload. Invalid values are rejected with `CONTENT__MEDIA_ILLEGAL_FILE_NAME`. Writing back the unchanged stored value is still accepted.
* Changed `POST /api/_action/clone/{entity}/{id}` to validate `path` and `fileName` of `media` and `path` of `media_thumbnail` in `overwrites`, also inside nested associations and `extensions`. Invalid values are rejected with `400` and `FRAMEWORK__API_INVALID_CLONE_OVERWRITE`. Calling `EntityRepository::clone()` from code is not affected.
* Added `Shopware\Core\Content\Media\MediaException::illegalPath()` and the constant `MediaException::MEDIA_ILLEGAL_PATH`.
* Added `Shopware\Core\Framework\Api\ApiException::invalidCloneOverwrite()` and the constant `ApiException::API_INVALID_CLONE_OVERWRITE`.

___

# Core

* Changed media deletion, file replacement, renaming, file loading, private downloads and mail attachments to never pass external media URLs to the filesystem. External media in `mediaIds` of `MailService::send()` are no longer attached and a warning is logged instead. Attachment URLs that are external URLs are also skipped when the mail is sent. Renaming an external media only updates its `fileName`.
* Added `Shopware\Core\Content\Mail\MailException::salesChannelNotFound()`, which `MailService` now uses instead of creating `SalesChannelNotFoundException` directly. The thrown exception is unchanged.

___

# Upgrade Information

## Media paths and file names are validated on API writes

Admin API, Sync API, apps and integrations can no longer set or change `media.path` or `media_thumbnail.path` to a relative path.
This affects the external file upload flow described in `UPGRADE-6.5.md`.
To store a file in the shop's own storage, use the media upload actions.
External media keep working with an absolute `http` or `https` URL.

The update does not rewrite values stored before it.
Check `media.path` and `media_thumbnail.path` for relative values that the configured path strategy does not produce, for example values containing `..` or a backslash, or starting with `/`.
Correct such paths before deleting or replacing the affected media, because both act on the stored path.
