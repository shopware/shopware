import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-country-state-detail',
    load: () => import('./src/sw-country-state-detail'),
    public: {
        props: [
            'countryState',
        ],
        emits: [
            'attribute-edit-cancel',
            'attribute-edit-save',
        ],
        blocks: [
            'sw_country_state_detail',
            'sw_country_state_detail_modal',
            'sw_country_state_detail_modal_technical_name',
            'sw_country_state_detail_modal_shortcode',
            'sw_country_state_detail_modal_position',
            'sw_country_state_detail_modal_footer',
            'sw_country_state_detail_modal_footer_cancel',
            'sw_country_state_detail_modal_footer_save',
        ],
        computed: [
            'modalTitle',
            'tooltipSave',
        ],
        methods: [
            'onCancel',
            'onSave',
        ],
    },
});
