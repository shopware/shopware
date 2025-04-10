---
title: Using external URL for media's path without storing physical files
issue: NEXT-39388
---
# Core
* Removed `WriteProtected` flag of `fileName` and `mimeType` fields in `\Shopware\Core\Content\Media\MediaDefinition` class
* Changed `generate` method in `\Shopware\Core\Content\Media\Infrastructure\Path\MediaUrlGenerator` class to load media from external URL by assigning the `path` field to `url` instead of generating a `url`.
___
# Upgrade Information
## Using external URL for media's path
You can now store media paths as external URLs using the admin API. This allows for more flexible media management without the need to store physical files on the server.

**Example Request:**

```http
POST http://sw.test/api/media
Content-Type: application/json

{
    "id": "01934e0015bd7174b35838bbb30dc927",
    "mediaFolderId": "01934ebfc0da735d841f38e8e54fda09",
    "path": "https://test.com/photo/2024/11/30/sunflowers.jpg",
    "fileName": "sunflower",
    "mimeType": "image/jpeg"
}
```
