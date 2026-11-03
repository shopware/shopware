import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-product-detail-base',
    load: () => import('./src/sw-product-detail-base'),
    public: {
        props: [
            'productId',
        ],
        blocks: [
            'sw_product_detail_base',
            'sw_product_detail_base_basic_info_card',
            'sw_product_detail_base_basic_info_form',
            'sw_product_detail_base_download_external_management',
            'sw_product_detail_base_price_card',
            'sw_product_detail_base_price_form',
            'sw_product_detail_base_advanced_prices_link',
            'sw_product_detail_deliverability_downloadable_info_card',
            'sw_product_detail_deliverability_downloadable_info_form',
            'sw_product_detail_deliverability_info_card',
            'sw_product_detail_deliverability_info_form',
            'sw_product_detail_base_category_card',
            'sw_product_detail_base_category_form',
            'sw_product_detail_base_media',
            'sw_product_detail_base_media_card',
            'sw_product_detail_base_media_card_title',
            'sw_product_detail_base_media_card_form',
            'sw_product_detail_base_media_modal',
            'sw_product_detail_base_settings_card',
            'sw_product_detail_base_settings_form',
            'sw_product_detail_base_guarantee_card',
            'sw_product_detail_base_guarantee_form',
        ],
        computed: [
            'product',
            'isDownloadExternallyManaged',
            'parentProduct',
            'customFieldSets',
            'loading',
            'isLoading',
            'showModeSetting',
            'productStates',
            'productType',
            'isDownloadCardVisible',
            'mediaFormVisible',
            'productMediaRepository',
            'mediaDefaultFolderRepository',
            'mediaDefaultFolderCriteria',
        ],
        methods: [
            'createdComponent',
            'onDownloadExternalManagementChange',
            'showRequiredDownloadsError',
            'showProductCard',
            'getMediaDefaultFolderId',
            'mediaRemoveInheritanceFunction',
            'mediaRestoreInheritanceFunction',
            'onOpenMediaModal',
            'onCloseMediaModal',
            'onOpenDownloadMediaModal',
            'onCloseDownloadMediaModal',
            'onAddMedia',
            'addMedia',
            'isSpatial',
            'isExistingMedia',
            'setMediaAsCover',
        ],
    },
});
