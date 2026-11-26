---
title: Invalidate cache tags on media replace
---

# Core

* Changed `Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber::invalidateMedia()` to also invalidate the listings and dynamic product groups containing products that use the media, categories using it as category image, layouts using it as section or block background or in an image, image slider, image gallery, YouTube, Vimeo or manufacturer logo element, categories, products and landing pages using it in their layout overrides, and product pages of manufacturers using it as logo. Replaced images show up on these pages without clearing the HTTP cache. Pages cached before the update need one `cache:clear:http`.
* Added migration `Core\Migration\V6_6\Migration1794315371AddSlotConfigMediaUsageTables`, which adds the tables `cms_page_media_usage`, `category_translation_media_usage`, `product_translation_media_usage` and `landing_page_translation_media_usage`. The indexers of the same names with the suffix `.indexer` fill them for the existing content.
* Added `Shopware\Core\Content\Media\Cms\CmsElementMediaIdsProviderInterface`. Services implementing it are tagged with `shopware.cms.element_media_ids_provider` automatically and return the media ids of a CMS element type, so replacing those media invalidates the pages showing the element. The first provider supporting an element type is used.
* Added `Shopware\Core\Content\Media\Cms\CmsElementMediaIdsProvider`, which reads the media ids of an element from its config by paths like `sliderItems.*.mediaId`. Elements of plugins showing media should register one:
```xml
<service id="my_plugin.cms.element_media_ids_provider.my_gallery" class="Shopware\Core\Content\Media\Cms\CmsElementMediaIdsProvider">
    <argument type="service" id="MyPlugin\Cms\MyGalleryCmsElementResolver"/>
    <argument type="collection">
        <argument>slides.*.picture.media.*.id</argument>
    </argument>
    <tag name="shopware.cms.element_media_ids_provider"/>
</service>
```
* Added `Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::buildManufacturerTag()`. Product detail pages are tagged with the manufacturer of the product.
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory::createIterator()` to iterate translation definitions by the id of the entity they belong to and to filter them by its version.
