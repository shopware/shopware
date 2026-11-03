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
    name: 'sw-settings-tag-detail-modal',
    load: () => import('./src/sw-settings-tag-detail-modal'),
    public: {
        props: [
            'editedTag',
            'counts',
            'property',
            'entity',
        ],
        emits: [
            'close',
            'finish',
        ],
        blocks: [
            'sw_settings_tag_detail_modal',
            'sw_settings_tag_detail_modal_tabs',
            'sw_settings_tag_detail_modal_mt_tabs',
            'sw_settings_tag_detail_modal_mt_tabs_content',
            'sw_settings_tag_detail_modal_mt_tabs_general_tab',
            'sw_settings_tag_detail_modal_mt_tabs_assignments_tab',
            'sw_settings_tag_detail_modal_tabs_general',
            'sw_settings_tag_detail_modal_tabs_assignments',
            'sw_settings_tag_detail_modal_tabs_general_tab',
            'sw_settings_tag_detail_modal_tabs_assignments_tab',
            'sw_settings_tag_detail_modal_footer',
            'sw_settings_tag_detail_modal_cancel',
            'sw_settings_tag_detail_modal_confirm',
        ],
        computed: [
            'tagRepository',
            'tagDefinition',
            'title',
            'allowSave',
            'tagDetailModalTabs',
            'tooltipSave',
            'computedCounts',
        ],
        methods: [
            'createdComponent',
            'onSave',
            'onCancel',
            'addAssignment',
            'removeAssignment',
        ],
    },
});
