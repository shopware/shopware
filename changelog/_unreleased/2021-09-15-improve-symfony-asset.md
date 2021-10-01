---
title: Improve Symfony Asset
issue: NEXT-16879
---
# Core
* Changed default asset `asset` to use Flysystem version strategy to add timestamp on linked urls
* Changed generated bundle assets like `@Administration` to use the Flysystem version strategy to add timestamp on linked urls
* Added new `PrefixVersionStrategy` class to allow versioning in prefixed url packages

___
# Upgrade Information

## Symfony Asset Version Strategy construction moved to dependency injection container

To be able to decorate the Symfony asset versioning easier, you can now decorate the service in the DI container instead of overwriting the service where it will be constructed.

Shopware offers by default many assets like `theme`, all those assets have an own version strategy service in the di like `shopware.asset.theme.version_strategy`

This can be decorated in the DI and the new class needs to implement the `\Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface` interface.
Here is an example to build the version strategy with the content instead of timestamps
```php
<?php declare(strict_types=1);

class Md5ContentVersionStrategy implements VersionStrategyInterface
{
    private FilesystemInterface $filesystem;

    private TagAwareAdapterInterface $cacheAdapter;

    private string $cacheTag;

    public function __construct(string $cacheTag, FilesystemInterface $filesystem, TagAwareAdapterInterface $cacheAdapter)
    {
        $this->filesystem = $filesystem;
        $this->cacheAdapter = $cacheAdapter;
        $this->cacheTag = $cacheTag;
    }

    public function getVersion(string $path)
    {
        return $this->applyVersion($path);
    }

    public function applyVersion(string $path)
    {
        try {
            $hash = $this->getHash($path);
        } catch (FileNotFoundException $e) {
            return $path;
        }

        return $path . '?' . $hash;
    }

    private function getHash(string $path): string
    {
        $cacheKey = 'metaDataFlySystem-' . md5($path);

        /** @var ItemInterface $item */
        $item = $this->cacheAdapter->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $hash = md5($this->filesystem->read($path));

        $item->set($hash);
        $item->tag($this->cacheTag);
        $this->cacheAdapter->saveDeferred($item);

        return $item->get();
    }
}
```
