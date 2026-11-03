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
    name: 'sw-product-modal-variant-generation',
    load: () => import('./src/sw-product-modal-variant-generation'),
    public: {
        props: [
            'product',
            'groups',
            'selectedGroups',
            'actualStatus',
        ],
        emits: [
            'modal-close',
            'variations-finish-generate',
        ],
        blocks: [
            'sw_product_modal_variant_generation',
            'sw_product_modal_variant_generation_sidebar',
            'sw_product_modal_variant_generation_sidebar_tabs',
            'sw_product_modal_variant_generation_sidebar_mt_tabs',
            'sw_product_modal_variant_generation_sidebar_tabs_items',
            'sw_product_modal_variant_generation_sidebar_tabs_item_options',
            'sw_product_modal_variant_generation_sidebar_tabs_item_prices',
            'sw_product_modal_variant_generation_sidebar_tabs_item_restrictions',
            'sw_product_modal_variant_generation_sidebar_descriptions',
            'sw_product_modal_variant_generation_sidebar_descriptions_options',
            'sw_product_modal_variant_generation_sidebar_add_only_selected',
            'sw_product_modal_variant_generation_sidebar_descriptions_restrictions',
            'sw_product_modal_variant_generation_sidebar_descriptions_prices',
            'sw_product_modal_variant_generation_main',
            'sw_product_modal_variant_generation_main_configurator_selection',
            'sw_product_modal_variant_generation_main_configurator_prices',
            'sw_product_modal_variant_generation_main_configurator_restrictions',
            'sw_product_modal_variant_generation_footer',
            'sw_product_modal_variant_generation_footer_cancel',
            'sw_product_modal_variant_generation_footer_generate',
            'sw_product_modal_variant_generation_download_external_management_switch',
        ],
        computed: [
            'variantGenerationTabs',
            'currencies',
            'uploadCardDescription',
            'productRepository',
            'optionRepository',
            'mediaRepository',
            'progressInPercentage',
            'progressMessage',
            'buttonVariant',
            'buttonLabel',
            'isGenerateButtonDisabled',
        ],
        methods: [
            'createdComponent',
            'beforeUnmountComponent',
            'onQueuesHandler',
            'onProgressMaxHandler',
            'onProgressActualHandler',
            'removeFile',
            'removeFileForAllVariants',
            'getList',
            'handlePageChange',
            'generateVariants',
            'showNextStep',
            'calcVariantsNumber',
            'onChangeAllVariantValues',
            'onChangeVariantValue',
            'isUploadDisabled',
            'isExistingMedia',
            'successfulUpload',
            'updateUsageForAllVariantFiles',
            'pushFileToUsageList',
            'onModalCancel',
            'addOriginalConfiguratorSettings',
            'emptyConfiguratorSettings',
            'onTermChange',
            'removeDuplicateEntries',
        ],
    },
});
