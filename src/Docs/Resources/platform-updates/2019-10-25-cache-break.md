[titleEn]: <>(Refactoring of entity cache)

## Di container tag changed
We have changed the cache configuration of the entity cache. It is no longer available under the service id `shopware.cache` but under `cache.object`.
However, a `\Symfony\Component\Cache\Adapter\TagAwareAdapterInterface` is still available.
The `cache.object` is now symfony compatible and is configured by default as follows:
```yaml
framework:
    cache:
        pools:
            cache.object:
                adapter: cache.adapter.filesystem
                tags: true
```

## Cache invalidation changed
In addition to the `cache.object` we now also have a `cache.http` which is used for the storefront HTTP cache.
This is configured by default as follows
```yaml
framework:
    cache:
        pools:
            cache.http:
                adapter: cache.adapter.filesystem
                tags: true
```

Since there can be multiple cache pools that work with tags, it is important to invalidate all these pools as well. 
For this you can use the `\Shopware\Core\Framework\Cache\CacheClearer::invalidateTags` function. The service can be injected normally via the DI container.