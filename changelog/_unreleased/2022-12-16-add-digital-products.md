---
title: Add digital products
issue: NEXT-20706
author: Krispin Lütjann
author_email: k.luetjann@shopware.com
author_github: King-of-Babylon
---

# Core
* Added functions `getStates` and `setStates` to `Shopware\Core\Checkout\Cart\LineItem\LineItem`
* Added function `hasLineItemWithState` to `Shopware\Core\Checkout\Cart\LineItem\LineItemCollection`
* Added new classes:
  * `Shopware\Core\Checkout\Cart\Order\LineItemDownloadLoader`
  * `Shopware\Core\Checkout\Cart\Rule\LineItemProductStatesRule`
  * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractDownloadRoute`
  * `Shopware\Core\Checkout\Customer\SalesChannel\DownloadRoute`
  * `Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection`
  * `Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadDefinition`
  * `Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadEntity`
  * `Shopware\Core\Content\Flow\Dispatching\Action\GrantDownloadAccessAction`
  * `Shopware\Core\Content\Media\File\DownloadResponseGenerator`
  * `Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadCollection`
  * `Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition`
  * `Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadEntity`
  * `Shopware\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTask`
  * `Shopware\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTaskHandler`
  * `Shopware\Core\Content\Product\DataAbstractionLayer\StatesUpdater`
  * `Shopware\Core\Content\Product\DataAbstractionLayer\UpdatedStates`
  * `Shopware\Core\Content\Product\Events\ProductStatesBeforeChangeEvent`
  * `Shopware\Core\Content\Product\Events\ProductStatesChangedEvent`
  * `Shopware\Core\Content\Product\State`
* Added functions `filterGoodsFlat` and `hasLineItemWithState` to `Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection`
* Added new fields `states` and `downloads` to `Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition`
* Added new properties `states` and `downloads` to `Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity` with getters and setters
* Added new fields `productDownloads` and `downloads` to `Shopware\Core\Content\Media\MediaDefinition`
* Added new properties `productDownloads` and `orderLineItemDownloads` to `Shopware\Core\Content\Media\MediaEntity` with getters and setters
* Added new fields `states` and `downloads` to `Shopware\Core\Content\Product\ProductDefinition`
* Added new properties `states` and `downloads` to `Shopware\Core\Content\Product\ProductEntity` with getters and setters
* Added new configuration option `private_allowed_extensions` to `Framework/Resources/config/packages/shopware.yaml`
* Added new function `choice` to `Shopware\Core\Framework\Rule\RuleConstraints`
* Added new mail template type `downloads_delivery`
* Added new column `states` to `product` table
* Added new table `product_download`
* Added new delivery time `Instant download`
* Added new default media folder for `Product downloads`
* Added new rule `Cart/Order with digital products`
* Added new flow `Deliver ordered product downloads`
* Added new column `states` to `order_line_item` table
* Added new table `order_line_item_download`
* Added new property `states` to the mapping in `Shopware\Elasticsearch\Product\ElasticsearchProductDefinition`
___
# Administration
* Changed `sw-media-compact-upload-v2` component to support multiselect and upload to private filesystem
* Added new props to `sw-media-compact-upload-v2`:
    * `disableDeletionForLastItem`
    * `sourceMultiselect`
    * `removeButtonLabel`
* Added new computed to `sw-media-compact-upload-v2`:
    * `mediaPreview`
    * `removeFileButtonLabel`
    * `isDeletionDisabled`
* Added new function `getFileName` to `sw-media-compact-upload-v2`
* Added new computed to `sw-media-preview-v2`:
    * `placeholderIconPath`
    * `lockIsVisible`
* Changed `sw-media-upload-v2` component to support multiselect and upload to private filesystem
* Added new props to `sw-media-upload-v2`:
    * `addFilesOnMultiselect`
    * `buttonLabel`
    * `privateFilesystem`
    * `required`
* Added new computed to `sw-media-upload-v2`:
    * `swFieldLabelClasses`
    * `buttonFileUploadLabel`
* Changed `sw-product-modal-variant-generation` to handle digital products in a second modal step
* Added new prop `actualStatus` to `sw-product-modal-variant-generation`
* Added new computed to `sw-product-modal-variant-generation`:
    * `optionRepository`
    * `mediaRepository`
    * `buttonLabel`
    * `isGenerateButtonDisabled`
* Added new watch `variantGenerationQueue` to `sw-product-modal-variant-generation`
* Added new functions to `sw-product-modal-variant-generation`
  * `removeFile`
  * `removeFileForAllVariants`
  * `getList`
  * `handlePageChange`
  * `showNextStep`
  * `onChangeAllVariantValues`
  * `onChangeVariantValue`
  * `isUploadDisabled`
  * `isExistingMedia`
  * `successfulUpload`
  * `pushFileToUsageList`
  * `onTermChange`
* Changed `sw-product-variants-overview` to handle with digital products
* Added new prop `productStates` to `sw-product-variants-overview`
* Added new computed `sw-product-variants-overview`:
    * `mediaRepository`
    * `productDownloadRepository`
* Added new watch `productStates` to `sw-product-variants-overview`
* Added new functions to `sw-product-variants-overview`:
    * `removeFile`
    * `mediaExists`
    * `successfulUpload`
    * `getDownloadsSource`
    * `variantIsDigital`
* Added new functions `saveVariants` and `generateVariants` to `Resources/app/administration/src/module/sw-product/helper/sw-products-variants-generator.js`
* Deprecated function `createNewVariants` in `Resources/app/administration/src/module/sw-product/helper/sw-products-variants-generator.js`
* Added new prop `creationStates` to `sw-product-detail`
* Added new functions to `sw-product-detail`:
    * `adjustProductAccordingToType`
    * `customValidate`
* Added new getter `productStates` and setter `setCreationStates` to `Resources/app/administration/src/module/sw-product/page/sw-product-detail/state.js`
* Added new function `productIsDigital` to `sw-product-list`
* Added new computed `productDownloadRepository` to `sw-product-detail-base` 
* Added new functions to `sw-product-detail-base`:
    * `onOpenDownloadMediaModal`
    * `onCloseDownloadMediaModal`
* Changed `sw-product-detail-variants` to use tabs `all`, `physical` and `digital`
* Added new computed `currentProductStates` to `sw-product-detail-variants`
* Added new function `setActiveTab` to `sw-product-detail-variants`
* Added new components:
    `sw-flow-grant-download-access-modal`
    `sw-product-deliverability-downloadable-form`
    `sw-product-download-form`
* Added new media preview icons:
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-ai.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-avi.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-broken.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-csv.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-doc.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-gif.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-jpg.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-mov.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-mp4.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-normal.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-pdf.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-ppt.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-svg.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-txt.svg`
    * `Resources/app/administration/static/img/media-preview/icons-multicolor-file-thumbnail-xls.svg`
___
# Storefront
* Changed storefront to handle with digital products
* Added new controller `Shopware\Storefront\Controller\DownloadController`
* Added new templates:
    * `src/Storefront/Resources/views/storefront/component/line-item/element/download-item.html.twig`
    * `src/Storefront/Resources/views/storefront/component/line-item/element/downloads.html.twig`
