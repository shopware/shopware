---
title: Allow app developers to extend the CMS by adding custom blocks  
issue: NEXT-14408  
flag: FEATURE_NEXT_14408  
---
# Core
* Added new entity `app_cms_block` in `Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockEntity`
* Added new tables
    * `app_cms_block`
    * `app_cms_block_translation`
* Added new classes
    * `Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockCollection`
    * `Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockDefinition`
    * `Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockEntity`
    * `Shopware\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationCollection`
    * `Shopware\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationDefinition`
    * `Shopware\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationEntity`
    * `Shopware\Core\Framework\App\Api\AppCmsController`
    * `Shopware\Core\Framework\App\Cms\Xml\Block`
    * `Shopware\Core\Framework\App\Cms\Xml\Blocks`
    * `Shopware\Core\Framework\App\Cms\Xml\Config`
    * `Shopware\Core\Framework\App\Cms\Xml\DefaultConfig`
    * `Shopware\Core\Framework\App\Cms\Xml\Slot`
    * `Shopware\Core\Framework\App\Cms\AbstractBlockTemplateLoader`
    * `Shopware\Core\Framework\App\Cms\BlockTemplateLoader`
    * `Shopware\Core\Framework\App\Cms\CmsExtensions`
    * `Shopware\Core\Framework\App\Exception\AppCmsExtensionException`
    * `Shopware\Core\Framework\App\Lifecycle\Persister\CmsBlockPersister`
* Added abstract method `Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader::getCmsExtensions`
* Added method `Shopware\Core\Framework\App\Lifecycle\AppLoader::getCmsExtensions`
* Updated private method to `Shopware\Core\Framework\App\Lifecycle\AppLifeCycle::updateApp` to persist CMS blocks provided by app
* Added new XML schema definition `cms-1.0.xsd`
* Updated `Shopware\Core\Framework\App\AppDefinition::defineFields` with new one-to-many association towards `AppCmsBlockDefinition` 
* Added new property `Shopware\Core\Framework\App\AppEntity::$cmsBlocks`
___
# API
* Added route `/api/app-system/cms/blocks` to retrieve custom CMS blocks provided by **activated** apps
___
# Upgrade Information
Existing apps **DO NOT** break with the introduced changes as they are backwards compatible.

If you implement `Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader` make sure to add the new method `::getCmsExtensions`.
