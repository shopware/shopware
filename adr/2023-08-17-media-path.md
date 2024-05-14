---
title: Media path rewrite
date: 2023-08-17
area: core
tags: [media, url, strategy]
---

## Context
In the current media system it is possible to configure different `Shopware\Core\Content\Media\Pathname\PathnameStrategy\PathnameStrategyInterface`.

These strategies are used to store files, which are uploaded for media entity, under a certain path.

The configured strategy is then also used to generate the URL that is used in the frontend/store API to embed the file.

The generation of this URL is currently triggered in an event subscriber which is registered to the `media.loaded` event.

For generating the URL an implementation of the `UrlGeneratorInterface` is used.
```php
interface UrlGeneratorInterface
{
    public function getAbsoluteMediaUrl(MediaEntity $media): string;

    public function getRelativeMediaUrl(MediaEntity $media): string;

    public function getAbsoluteThumbnailUrl(MediaEntity $media, MediaThumbnailEntity $thumbnail): string;

    public function getRelativeThumbnailUrl(MediaEntity $media, MediaThumbnailEntity $thumbnail): string;
}

interface PathnameStrategyInterface
{
    public function getName(): string;

    /**
     * Generate a hash, missing from url if omitted
     */
    public function generatePathHash(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): ?string;

    /**
     * Generate the cache buster part of the path, missing from url if omitted
     */
    public function generatePathCacheBuster(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): ?string;

    /**
     * Generate the filename
     */
    public function generatePhysicalFilename(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): string;
}
```

## Issues

* `PathnameStrategyInterface` as well as `UrlGeneratorInterface` have a dependency on the DAL and always need a fully loaded entity to generate the URL. This is a big overhead when you consider what data is (currently) needed for the URL generation in the end.
* The media upload "must" always be done via the shopware application, so that the folder structure stored in the file system and generated in the URL match. So it is only conditionally (or not at all) possible to upload all media directly to a S3 CDN without uploading the files via the shopware stack.
* In theory, the strategy must never be reconfigured after a file has been uploaded. If the file is uploaded to `/foo/test.jpg` and then the strategy is changed to one that would place the same file under `/bar/test.jpg`, the new strategy will take effect when the URL is generated, but the file will never be moved in the filesystem.
* The current strategies use a so called "cache busting" system, where the "uploaded-at" value is included in the file path. However, this does not work if the URL has been statically included in the CMS. Here, replacing the media file always leads to a new file path and the image can no longer be reached under the old file path.

## Decision
To address the issues listed above, we will make the following changes to the system:

- The file path will be saved directly to the media entity (and thumbnail) when the file is uploaded.
    - This way we don't have to access the strategy when generating the URL, which might have changed in the meantime.
- We allow to change the file path via API and write it directly when creating the entity
    - This way files can be synchronized directly with an external storage and the path only has to be changed in the entity or given during import.
- To generate the strategy we use new location structs, which can be easily created via a service.
    - So we remove the dependency to the DAL to generate the file path.
- For generating the URL, we implement a new service, which can be operated without entities.
    - The URL generation is more resource efficient and can be done without fully loaded entities.
- The new URL generator uses a new "cache busting" system, which writes the updated at timestamp of the media entity as query parameter into the URL.
    - This way the file path can remain the same even if the file is updated. Changes to the entity's meta data will also result in a new URL.
    
## BC promise

For the Backwards compatibility we will take the following measures:

- Until 6.6.0 the old URL generator will still be used, which generates the URL with the old strategy.
- From 6.6.0 the new URL generator will be used, which generates the URL based on the `MediaEntity::$path`.
- If a project specific strategy is used, it must be migrated to the new pattern by 6.6.0.
- We provide a `BCStrategy` which converts the new format to the old format.
- For an easy transition between the majors, we make it possible to always use `MediaEntity::$path` for the relative path. We realize this via an entity loaded subscriber, which generates the value at runtime via the URL generator and writes it to the path property.

## Consequences

- We need less resources to generate the absolute media URLs
- We can generate the URLs even without fully loaded entities
- The strategy can be changed over time without moving the files in the file system
- We can load the files directly to an external storage and adjust the path in the entity
- We remove the dependency to the DAL from the strategy and the URL generator.

## Example

```php
<?php 

namespace Examples;

use Shopware\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;use Shopware\Core\Content\Media\Core\Params\UrlParams;use Shopware\Core\Content\Media\MediaCollection;use Shopware\Core\Content\Media\MediaEntity;use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;

class BeforeChange
{
    private UrlGeneratorInterface $urlGenerator;
    
    public function foo(MediaEntity $media) 
    {
        $relative = $this->urlGenerator->getRelativeMediaUrl($media);
        
        $absolute = $this->urlGenerator->getAbsoluteMediaUrl($media);
    }
    
    public function bar(MediaThumbnailEntity $thumbnail) 
    {
        $relative = $this->urlGenerator->getRelativeThumbnailUrl($thumbnail);
        
        $absolute = $this->urlGenerator->getAbsoluteThumbnailUrl($thumbnail);
    }
}

class AfterChange
{
    private AbstractMediaUrlGenerator $generator;
    
    public function foo(MediaEntity $media) 
    {
        $relative = $media->getPath();

        $urls = $this->generator->generate([UrlParams::fromMedia($media)]);
        
        $absolute = $urls[0];
    }
    
    public function bar(MediaThumbnailEntity $thumbnail) 
    {
        // relative is directly stored at the entity
        $relative = $thumbnail->getPath();

        // path generation is no more entity related, you could also use partial entity loading and you can also call it in batch, see below
        $urls = $this->generator->generate([UrlParams::fromMedia($media)]);
        
        $absolute = $urls[0];
    }
    
    public function batch(MediaCollection $collection) 
    {
        $params = [];
        
        foreach ($collection as $media) {
            $params[$media->getId()] = UrlParams::fromMedia($media);
            
            foreach ($media->getThumbnails() as $thumbnail) {
                $params[$thumbnail->getId()] = UrlParams::fromThumbnail($thumbnail);
            }
        }
        
        $urls = $this->generator->generate($paths);

        // urls is a flat list with {id} => {url} for media and also for thumbnails        
    }
}

class ForwardCompatible
{
    // to have it forward compatible, you can use the Feature::isActive('v6.6.0.0') function
    public function foo(MediaEntity $entity) 
    {
        // we provide an entity loaded subscriber, which assigns the url of
        // the UrlGeneratorInterface::getRelativeMediaUrl to the path property till 6.6
        // so that you always have the relative url in the MediaEntity::path proprerty 
        $path = $entity->getPath();
        
        if (Feature::isActive('v6.6.0.0')) {
            // new generator call for absolute url
        } else {
            // old generator call for absolute url
        }
    }
}
```
