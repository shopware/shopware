# 2023-01-06 - Upload Thumbnails API

## Context
Currently, it’s not possible to upload Thumbnails via the API. All Thumbnails are processed by Shopware itself. There’s no way to upload your optimized Thumbnails via the API.

## Decision
### Migration
Add a new column `isUploaded` into the media_thumbnail table. This flag lets us know if the thumbnail is uploaded or generated.

### Core
Add a new `uploadThumbnail` API in Shopware\Core\Content\Media\Api\MediaUploadController
```php
/**
 * @Route("/api/_action/media/{mediaId}/upload-thumbnail", name="api.action.media.upload-thumbnail", methods={"POST"})
 */
public function uploadThumbnail(string $mediaId, Request $request, Context $context, ResponseFactoryInterface $responseFactory): Response
{
    // Based on mediaId, validate uploaded thumbnails has to map with the media thumbnail sizes

    // call thumbnail service to upload thumbnails
}
```

Add a new function in Shopware\Core\Content\Media\Thumbnail\ThumbnailService
```php
public function uploadThumbnails(string $mediaId, ThumbnailCollection $uploadedThumbnails, Context $context): void
{
    // we might need to check if thumbnails of the $mediaId have already been uploaded,
    // should we have the option to skip or replace them?
    foreach ($uploadedThumbnails as $thumbnail) {
        // write thumbnail with the prefix `uploaded_` . $fileName base on the isUploaded flag
        // insert new thumbnail in to media_thumbnail with uploaded flag
    }
}
```

Change function `AbstractPathNameStrategy::generatePhysicalFilename` to add the prefix for the thumbnail filename if `isUploaded` flag is true
```php
/**
  * {@inheritdoc}
  */
public function generatePhysicalFilename(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): string
{
    $filenameSuffix = '';
    $filenamePrefix = '';
    if ($thumbnail !== null) {
        $filenameSuffix = sprintf('_%dx%d', $thumbnail->getWidth(), $thumbnail->getHeight());

        if ($thumbnail->isUploaded()) {
            $filenamePrefix = 'uploaded_';
        }
    }

    $extension = $media->getFileExtension() ? '.' . $media->getFileExtension() : '';

    return $filenamePrefix . $media->getFileName() . $filenameSuffix . $extension;
}
```

IMHO, we need to add a prefix to prevent duplicate filenames with generated thumbnails.
E.g. Shopware already generated the thumbnail name `red-variant-shirt_400x400.png`, then our upload file should be `something_red-variant-shirt_400x400.png`.

## Consequences
This change provides a new possibility for external services to get control of the application thumbnail behaviour
