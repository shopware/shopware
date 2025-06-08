---
title: Atomic theme compilation
date: 2023-01-10
area: storefront
tags: [theme, storefront, performance]
---

## Context

The theme compilation could result in a broken storefront, when there is some error during theme compilation (e.g. because of wrongly configured values in the theme configuration by the customer).
The reason is that the theme is always compiled into the same physical folder/location and at the start of the compilation this folder will be deleted and recreated, but when the compilation crashes due to errors, not all needed files are present in the theme folder and the storefront UI is broken.

Another issue was that there were edge cases when you hit a shop where the theme compilation was in progress, the storefront also may look broken as not all required compiled files are present in the theme folder (yet).

## Decision

Instead of compiling the theme always in the same folder we will compile the theme always in a new folder with a generated seed.
Until the theme compilation is completed and successful the old theme will be used in the storefront UI, only when the theme compilation is finished we will use the new theme folder in the storefront.

This will also open up the possibility for further new features where the customer may manually rollback to previous theme compiled version.
But for now we will delete the old theme folder one hour (see [caching](#caching)) after the theme was compiled successfully to the new location and we use the theme from the new location in the storefront.

### Discarded Alternatives

An alternative solution would be to always use the same folder location for the live version of the theme, but the theme compile process will not directly write into this folder, but in a temporary folder.
When the theme compilation finished we then copy the whole temp folder over to the live folder.

This approach works well under the assumptions that moving a whole folder is a fast and a atomic operation, which it is on most local filesystems.
But the theme assets can also be stored on external storage (e.g. S3, Google Cloud storage), especially for more dedicated setups it is common to store the asset files on a external filesystem/CDN.
And we can't assume that copying a whole folder is an atomic operation there, and in fact for S3 and Google Cloud Storage moving a folder means that we need to manually empty the target folder and move each file individually as both storages don't really support the concept of `folders`.
Even though this alternative fixed the issue when theme compilation errored, it does not fix the edge case when you hit a store where the current theme asset folder does not contain all needed folders yet. And as the files have to be copied one by one it would probably exaggerate that problem further.

Additionally, from a cost perspective the solution has some downsides, as on some (especially S3) external storages you not only pay for the storage itself, but also for file operations.
And using a temporary folder and then moving the folders will result in a lot more file operations, compared to directly compiling into the new folder.

Because of the aforementioned issues we decided to discard this approach.

## Consequences

We will expand the abstract class `AbstractThemePathBuilder` to allow for a `seeding` mechanism that allows to change the active theme folder path based on a randomly generated seed.

We add the two following methods that should be implemented in custom implementations of the `AbstractThemePathBuilder`.
```php
    public function generateNewPath(string $salesChannelId, string $themeId, string $seed): string
    {
    }

    public function saveSeed(string $salesChannelId, string $themeId, string $seed): void
    {
    }
```

During a theme compilation the theme compiler will generate a new random seed and call the `generateNewPath()` method with that seed, to get the location of the theme folder where the compiled files of that compilation will be stored.
After the theme compilation is finished successfully the compiler will call the `saveSeed()` method with the seed that was used for the compilation, after that subsequent calls to the existing `assemblePath()` method should take into account the new seed and thus the new theme folder should be used in the storefront.

### Backwards Compatibility

Those methods will be added as concrete methods in the abstract class with a default implementation, to not break backwards compatibility.
But both new methods are marked as `@deprecated`, because they will be abstract in the 6.6 major version, so custom implementations have to implement those methods for 6.6.
The default implementation to keep backwards compatibility will ignore the seed, so the `saveSeed()` method will be a no-op, and the `generateNewPath()` will just call the existing `assemblePath()` method, thus the behaviour for existing implementations will be the same as before this change.
This means that the old implementations also don't use the seeding mechanism, so the problems with the theme compilation will still be present in those implementations, unless the custom implementations also implement the seeding mechanism, by implementing the two new methods.

### Performance

The current `seed` has to be saved somewhere where it is fast to retrieve, as the `seed` value will be needed on every storefront request.
Therefore, we will store the `seed` in `system_config` table as the system config is already heavily cached and should not be a performance issue. Additionally it already allows saving values per sales channel which we need in this case.

We also considered storing the seed in an additional column in the `theme_sales_channel` mapping table and reading it in the `RequestTransformer` and then adding it as a `request attribute` for further usage.
This idea was discarded, because the DAL does not allow additional columns in mapping definitions, and in fact it will reset values in additional columns on every write as it uses `REPLACE INTO` queries to update the mappings.

### Caching

As the url to the assets now change this means that the cache for all storefront requests needs to be invalidated.
This was already the case previously as a new theme compilation would add a cache-buster query param to the url, to prevent the serving of stale theme files cached on the browser side.

But with the theme compilation, we can't delete the old theme folder immediately after the new theme compilation finished successfully, as the cache invalidation can take some time especially if you use external CDNs like fastly.
This means that we expect for a short time that clients still will request the old theme folder, because they are served stale content from the CDN.
To ensure that the site renders normally for those clients we won't delete the old theme folder immediately, but instead dispatch a queue message with a configurable delay (default 15 min / max SQS delay) that the old theme folder should be deleted.
So the old theme files will still be accessible for one hour after a new theme was compiled.

We can expand on this deletion strategy, once we implement further features like manual rollback to previous theme versions.

### Theme Assets

Previously the theme assets were stored in the same folder as the compiled theme files, that meant, that for every saleschannel where a theme was used the theme assets were duplicated, even though the assets are always the same, regardless of the theme configuration.
This does not scale, when we create new folders for the compiled theme files on the fly on every theme compile.
So the asset files are now stored in a separate folder, that is not dependent on the sales channel or the theme configuration.
We use the `themeId` as the folder name, so the assets are still unique per theme, but they are not duplicated for every sales channel.

### PaaS / Platform.sh

Platform.sh currently does not offer to store the theme assets on an internal storage, therefore the assets need to be stored locally.
Additionally, Platform.sh uses `immutable deploys`, meaning that once a version is deployed the file system is read-only and no changes can be made to the local files.

The theme compile is executed on PaaS during the `build` step, where there is no DB connection, so we can't use the new default implementation of the `AbstractThemePathBuilder`, which stores the new `seed` in the DB during the theme compile.
But because of the `immutable deploys` it is not possible to recompile the theme at runtime, a new deployment is needed to recompile the theme.
So PaaS was not affected by the issues during the theme compilation, and instead of rollbacking to a backup theme folder you would rollback to the last deployment instead.

That means that PaaS does not need the seeding mechanism, so we add a implementation for the `AbstractThemePathBuilder` that ignores the seed and will always return the same path for a given theme and sales channel combination (like the old default implementation).

Once Platform.sh allows to store the theme assets externally we can move the theme compile from the `build` to the `deploy` step and can then use the default `seeding` implementation, as we have access to the DB in the deploy step.
Then you can also recompile the theme at runtime and PaaS will also benefit from the new theme compile mechanism.
