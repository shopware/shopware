---
title: Media path storage
issue: NEXT-25584
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---
# Core
* Added new media path system where the path is generated externally or when the media file is uploaded. With this change we did the following deprecations and additions:
* Deprecated `UrlGeneratorInterface`, will be replaced with the `AbstractMediaUrlGenerator`. These classes are used to generate a media url
* Deprecated `AbstractPathNameStrategy`, will be replaced with `AbstractMediaPathStrategy`. These classes are responsible to generate the media path
* Added `AbstractMediaPathUpdater`, which acts as an event listener (but also can be used as service), which triggered when a media file is updated and triggeres the path storing
* Added `AbstractMediaLocationBuilder`, which acts as a factory class, to build the location structs (`MediaLocationStruct` and `ThumbnailLocationStruct`) for the path generation
* Added `MediaLocationEvent` and `ThumbnailLocationEvent`, which are dispatched when the location structs are build and processed to for the path generation
* Added `UpdatePath` command, which allows to loop all media and thumbnails, generate their path and store it in the database
* Added `\Shopware\Core\Framework\Struct\StateAwareTrait::state`, which allows to scope state changes
* Added new `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\PostUpdateIndexer` class, which allows to run indexer after the update process but exclude them from whole indexing processes.
___
# Upgrade Information
## Stored media path
Within the v6.5 lane, the media path handling changed in a way, where we store the path in the database instead of generating it always on-demand. 
They will be generated, when the media is uploaded. The path can also be provided via api to handle external file uploads.

We also removed the dependency to the entity layer and allow much faster and simpler access to the media path via location structs and a new url generator.
Due to this change, the usage of the `UrlGeneratorInterface` changed. The generator is deprecated and will be removed with v6.6.0. We implemented a new generator `MediaUrlGenerator` which can be used instead.

### Generating a media or thumbnail url

```php
<?php 

namespace Examples;

use Shopware\Core\Content\Media\Domain\Path\AbstractMediaUrlGenerator;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;

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

        $params = ['path' => $media->getPath(), 'updatedAt' => $media->getUpdatedAt()];
        
        $urls = $this->generator->generate([$params]);
        
        $absolute = $urls[0];
    }
    
    public function bar(MediaThumbnailEntity $thumbnail) 
    {
        // relative is directly stored at the entity
        $relative = $thumbnail->getPath();

        $params = ['path' => $media->getPath(), 'updatedAt' => $media->getUpdatedAt()];
        
        // path generation is no more entity related, you could also use partial entity loading and you can also call it in batch, see below
        $urls = $this->generator->generate([$params]);
        
        $absolute = $urls[0];
    }
    
    public function batch(MediaCollection $collection) 
    {
        $params = [];
        
        foreach ($collection as $media) {
            $params[$media->getId()] = ['path' => $media->getPath(), 'updatedAt' => $media->getUpdatedAt()];
            
            foreach ($media->getThumbnails() as $thumbnail) {
                $params[$thumbnail->getId()] = ['path' => $thumbnail->getPath(), 'updatedAt' => $thumbnail->getUpdatedAt()];
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
        if (Feature::isActive('v6.6.0.0')) {
            $urls[] = $mediaItem->getPath();
        } else {
            $urls[] = $this->urlGenerator->getRelativeMediaUrl($mediaItem);
        }
    }
}
```

### Path strategies
Beside the url generator change, we also had to change the media path strategy. The strategies are no longer working with a `MediaEntity`. They are now working with a `MediaFile` object. This object is a simple struct, which contains the path and the updated at timestamp. The path is the same as the one stored in the database. The updated at timestamp is the timestamp, when the path was generated. This is important for the cache invalidation. The `MediaFile` object is also used for the thumbnail generation. The thumbnail generation is now also working with a `MediaLocation` object instead.

As foundation, we use `\Shopware\Core\Content\Media\Domain\Path\AbstractMediaPathStrategy` as base class and dependency injection service id:

```php
<?php

namespace Examples;

class Before extends AbstractPathNameStrategy
{
    public function getName(): string
    {
        return 'filename';
    }

    public function generatePathHash(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): ?string
    {
        return $this->generateMd5Path($media->getFileName());
    }
}


class After extends AbstractMediaPathStrategy
{
    public function name(): string
    {
        return 'file_name';
    }

    protected function value(MediaLocationStruct|ThumbnailLocationStruct $location): ?string
    {
        return $location instanceof ThumbnailLocationStruct ? $location->media->fileName : $location->fileName;
    }

    protected function blacklist(): array
    {
        return ['ad' => 'g0'];
    }
}
```

It is no more necessary to call the path hashing by your own. All cache busting and other logic is done in the abstract implementation. The functions are now seperated and can be reused in your implementation.
The path is generated by 4 segments:

```php
$paths[$location->id] = implode('/', \array_filter([
    $type,
    $this->md5($this->value($location)),
    $this->cacheBuster($location),
    $this->physicalFilename($location),
]));
```

### Entity dependency
If you want, you can overwrite all of this parts by your own. The strategies are now using `MediaLocationStruct`s or `ThumbnailLocationStruct`s. 
These structs are simple structs, which contains the necessary information to generate the path. We also provide a builder class to simply generate this classes based on entity identifiers:

```php
<?php

namespace Examples;

use Shopware\Core\Content\Media\Core\Path\MediaLocationBuilder;use Shopware\Core\Content\Media\Domain\Path\AbstractMediaPathStrategy;

class Consumer
{
    private MediaLocationBuilder $builder;
    private AbstractMediaPathStrategy $strategy;
    
    public function foo(array $mediaIds)
    {
        $locations = $this->builder->buildLocations($mediaIds);
        
        $paths = $this->strategy->generate($locations);        
    }
}
```

If you implement your own strategy and you require more data, you can add an event listener for the `MediaLocationEvent` or `ThumbnailLocationEvent` which allows data manipulation for the provided structs.
