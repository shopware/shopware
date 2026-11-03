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
    name: 'sw-seo-url-template-card',
    load: () => import('./src/sw-seo-url-template-card'),
    public: {
        emits: [
            'sales-channel-changed',
        ],
        blocks: [
            'sw_seo_url_template_card',
            'sw_seo_url_template_card_info_box',
            'sw_seo_url_template_card_entries',
            'sw_seo_url_template_card_entries_input_wrapper',
            'sw_seo_url_template_card_entries_input',
            'sw_seo_url_template_card_entries_input_suffix',
            'sw_seo_url_template_card_entries_variables',
            'sw_seo_url_template_card_entries_preview',
            'sw_seo_url_template_card_entries_preview_label',
            'sw_seo_url_template_card_entries_preview_item',
        ],
        computed: [
            'salesChannelRepository',
            'currentSalesChannel',
            'salesChannelIsHeadless',
            'headlessSalesChannelHasExternalDomain',
            'salesChannelSupportsSeoUrlTemplates',
        ],
        methods: [
            'createdComponent',
            'fetchSeoUrlTemplates',
            'createSeoUrlTemplatesFromDefaultRoutes',
            'createVariableOptions',
            'getVariableOptions',
            'getLabel',
            'getPlaceholder',
            'onClickSave',
            'createSaveErrorNotification',
            'createSaveSuccessNotification',
            'onSelectInput',
            'onInput',
            'debouncedPreviewSeoUrlTemplate',
            'setErrorMessagesForEntity',
            'fetchSeoUrlPreview',
            'fetchSalesChannels',
            'onSalesChannelChanged',
            'getTemplatesForSalesChannel',
        ],
    },
});
