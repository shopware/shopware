---
title: Add ThumbnailGeneratedEvent, progressive JPEG thumbnails and resilient batch generation
author: Thomas Wunner
author_email: acc@wunner-software.de
author_github: @alpham8
---
# Core
* Added `Shopware\Core\Content\Media\Event\ThumbnailGeneratedEvent`, dispatched once per generated thumbnail in `Shopware\Core\Content\Media\Thumbnail\ThumbnailService`. It carries the `mediaId`, `thumbnailId`, thumbnail `path`, `mimeType`, the `FilesystemOperator` the thumbnail was written to and the `Context`, so subscribers can post-process a single thumbnail (e.g. lossless optimisation).
* Changed `Shopware\Core\Content\Media\Thumbnail\Processor\GdImageThumbnailProcessor` and `ImagickThumbnailProcessor` to write progressive (interlaced) JPEG thumbnails instead of baseline JPEGs.
* Changed `Shopware\Core\Content\Media\Thumbnail\ThumbnailService::generateAndSave()` to clean up already-written thumbnail files and re-throw when thumbnail generation fails, instead of silently swallowing the exception and leaving orphan files on disk.
* Changed `Shopware\Core\Content\Media\Thumbnail\ThumbnailService::generate()` to isolate failures per media: a single unprocessable media is logged and skipped so the remaining media in the batch still receive their thumbnails.
* Added a `Psr\Log\LoggerInterface` dependency to `Shopware\Core\Content\Media\Thumbnail\ThumbnailService`.
___
# Upgrade Information
## New `ThumbnailGeneratedEvent` for per-thumbnail post-processing
A new event `Shopware\Core\Content\Media\Event\ThumbnailGeneratedEvent` is dispatched after every single thumbnail is written to disk. Previously there was no extension point for post-processing an individual thumbnail — `MediaPathChangedEvent` only fires once for the whole batch.

Subscribe to it to post-process each thumbnail (for example, running `jpegoptim` or `pngquant`):

```php
use Shopware\Core\Content\Media\Event\ThumbnailGeneratedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ThumbnailOptimizerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ThumbnailGeneratedEvent::class => 'onThumbnailGenerated',
        ];
    }

    public function onThumbnailGenerated(ThumbnailGeneratedEvent $event): void
    {
        $filesystem = $event->getFilesystem();
        $optimized = $this->optimize($filesystem->read($event->getPath()), $event->getMimeType());
        $filesystem->write($event->getPath(), $optimized);
    }
}
```

## Progressive JPEG thumbnails
JPEG thumbnails are now written as progressive (interlaced) JPEGs. This improves perceived load time (LCP) and is transparent to consumers. No action is required.

## Resilient batch thumbnail generation
`ThumbnailService::generate()` no longer aborts the whole batch when a single media fails to process. The failing media is logged via the new logger dependency and skipped, and its partially written thumbnail files are cleaned up. The single-media path (`ThumbnailService::updateThumbnails()`) still propagates the exception to the caller.
