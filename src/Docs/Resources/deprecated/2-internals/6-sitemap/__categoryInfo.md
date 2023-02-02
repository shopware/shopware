[titleEn]: <>(Sitemap)
[hash]: <>(category:sitemap)

Shopware generates a default sitemap which is compressed and cached in the file system. To support shops
with a lot of products and categories the sitemap is splitted into multiple files and can be generated in the background.

# sitemap generation

You can customize the generation of the sitemap via the administration, a config file and with a plugin.

## Administration

In the administration you can define the cache time of the sitemap. If that timespan has exceeded, a new
sitemap is generated. Also you can define the method to create a new sitemap.

### scheduled
With this method the sitemap will be auto generated with a scheduled task on a regular base and stored in the file system.

### live
This method creates a sitemap upon request, if one of the following conditions is true:
- there isn't a sitemap yet
- the sitemap cache needs a refresh 

### manually
You have to generate the sitemap via command line on your own:

```
php bin/console sitemap:generate
```

Every time a new URL is added or an old one removed you have to run that command again.


## Excluding adding URLs

The default sitemap contains only products and categories but the implementation supports three ways to customize it:
- custom URLs via config file
- excluding URLs via config file
- extending with a plugin

you can find the config file at config/packages/shopware.yaml

### custom URLs

You should find a sitemap entry in your shopware.yaml. To add a custom URL to your sitemap, create a subentry with the following syntax:
```
sitemap:
   custom_urls:
       - { url: "https://shopware.com", lastMod: "2019-01-01 00:00:00", changeFreq: "weekly", priority: 0.5, salesChannelId: "mySalesChannelId"  }
```

- `url` contains your custom URL
- `lastMod` date of last modification in format Y-m-d H:i:s
- `changeFreq` how frequently the page is likely to change. Possible values: always, hourly, daily, weekly, monthly, yearly
- `priority` the priority of this URL relative to other URLs on your site. Must be a value between 0 and 1.
- `salesChannelId` the id of the sitemaps sales channel id to which the URL is added


### excluding URLs

Like adding URLs, you can also remove them via an subentry in you shopware.yaml file:
```
sitemap:
   excluded_urls:
       - { resource: Shopware\Core\Content\Product\ProductEntity, identifier: "aProductId", salesChannelId: "mySalesChannelId"  }
```

- `resource` the entity which should be excluded. Possible values: Shopware\Core\Content\Product\ProductEntity, Shopware\Core\Content\Category\CategoryEntity
- `identifier` the id of the entity
- `salesChannelId` the id of the sitemaps sales channel id from which the URL is excluded


### extending the sitemap generation
If you create content with a plugin and want it to appear in the sitemap, you can create a service which implements the `Shopware\Core\Content\Sitemap\UrlProviderInterface`
and tag it with the `shopware.sitemap_url_provider` tag.
When a new sitemap is generated it will be loaded and has to return a `Shopware\Core\Content\Sitemap\Struct\UrlResult`.
