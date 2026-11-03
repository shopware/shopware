import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-media-quickinfo-usage',
    load: () => import('./src/sw-media-quickinfo-usage'),
    public: {
        props: [
            'item',
            'routerLinkTarget',
        ],
        blocks: [
            'sw_media_quickinfo_usage',
            'sw_media_quickinfo_usage_loader',
            'sw_media_quickinfo_usage_empty_state',
            'sw_media_quickinfo_usage_list',
            'sw_media_quickinfo_usage_item',
            'sw_media_quickinfo_usage_item_icon',
            'sw_media_quickinfo_usage_item_label',
        ],
        computed: [
            'productRepository',
            'landingPageRepository',
            'categoryRepository',
            'cmsPageRepository',
            'moduleFactory',
            'slotConfigCriteria',
            'cmsPageBlockConfigCriteria',
            'getUsages',
            'isNotUsed',
        ],
        methods: [
            'createdComponent',
            'loadSlotConfigAssociations',
            'loadProductAssociations',
            'loadCategoryAssociations',
            'loadManufacturerAssociations',
            'loadMailTemplateAssociations',
            'loadDocumentBaseConfigAssociations',
            'loadAvatarUserAssociations',
            'loadPaymentMethodAssociations',
            'loadShippingMethodAssociations',
            'loadLayoutAssociations',
            'isExistedCmsMedia',
            'getProductUsage',
            'getCategoryUsage',
            'getManufacturerUsage',
            'getMailTemplateUsage',
            'getDocumentBaseConfigUsage',
            'getAvatarUserUsage',
            'getPaymentMethodUsage',
            'getShippingMethodUsage',
            'getLayoutUsage',
            'getLandingPageUsage',
            'getIconForModule',
        ],
    },
});
