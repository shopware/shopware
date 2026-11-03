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
    name: 'sw-cms-create-wizard',
    load: () => import('./src/sw-cms-create-wizard'),
    public: {
        props: [
            'page',
        ],
        emits: [
            'on-section-select',
            'wizard-complete',
        ],
        blocks: [
            'sw_cms_create_wizard',
            'sw_cms_create_wizard_first_step',
            'sw_cms_create_wizard_page_type',
            'sw_cms_create_wizard_page_type_options',
            'sw_cms_create_wizard_first_step_abort',
            'sw_cms_create_wizard_first_step_helptext',
            'sw_cms_create_wizard_second_step',
            'sw_cms_create_wizard_section',
            'sw_cms_create_wizard_second_step_back',
            'sw_cms_create_wizard_second_step_helptext',
            'sw_cms_create_wizard_third_step',
            'sw_cms_create_wizard_page_preview',
            'sw_cms_create_wizard_page_completion',
            'sw_cms_create_wizard_page_name',
            'sw_cms_create_wizard_page_completion_buttons',
            'sw_cms_create_wizard_page_completion_save_button',
            'sw_cms_create_wizard_third_step_back',
        ],
        computed: [
            'visiblePageTypes',
            'pageTypeSelectionStyle',
            'currentPageType',
            'isCustomEntityType',
            'isCompletable',
            'customEntities',
            'pagePreviewMedia',
            'pagePreviewStyle',
            'assetFilter',
            'cmsPageStore',
        ],
        methods: [
            'goToStep',
            'getStepName',
            'onPageTypeSelect',
            'onSectionSelect',
            'onCompletePageCreation',
        ],
    },
});
