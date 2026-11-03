import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-cms-page-form',
    load: () => import('./src/sw-cms-page-form'),
    public: {
        props: [
            'page',
            'elementUpdate',
        ],
        blocks: [
            'sw_cms_page_form',
            'sw_cms_page_form_content',
            'sw_cms_page_form_section',
            'sw_cms_page_form_section_name_wrapper',
            'sw_cms_page_form_section_name_icon',
            'sw_cms_page_form__section_name',
            'sw_cms_page_form_section_empty_state_block',
            'sw_cms_page_form_section_empty_state_block_text',
            'sw_cms_page_form_section_type_label_wrapper',
            'sw_cms_page_form_section_type_label',
            'sw_cms_page_form_section_type_label_icon',
            'sw_cms_page_form_section_type',
            'sw_cms_page_form_card',
            'sw_cms_page_form_card_content',
            'sw_cms_page_form_element_config',
        ],
        computed: [
            'cmsBlocks',
            'cmsElements',
            'slotPositions',
        ],
        methods: [
            'createdComponent',
            'sortSlots',
            'initVisibility',
            'getBlockTitle',
            'displaySectionType',
            'getSectionName',
            'getSectionPosition',
            'getDeviceActive',
            'displayNotification',
        ],
    },
});
