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
    name: 'sw-cms-section-config',
    load: () => import('./src/sw-cms-section-config'),
    public: {
        props: [
            'section',
        ],
        emits: [
            'section-delete',
            'section-duplicate',
        ],
        blocks: [
            'sw_cms_section_config',
            'sw_cms_section_config__quickactions',
            'sw_cms_section_config__quickaction_duplicate',
            'sw_cms_section_config__quickaction_delete',
            'sw_cms_section_config__settings',
            'sw_cms_section_config_name_field',
            'sw_cms_sidebar_section_config_css_classes_field',
            'sw_cms_sidebar_section_config_sizing_field',
            'sw_cms_sidebar_section_config_sidebar_mobile',
            'sw_cms_section_config_background_color_field',
            'sw_cms_section_config_background_image_field',
            'sw_cms_section_config_background_image_position_field',
        ],
        computed: [
            'uploadTag',
            'mediaRepository',
            'cmsPageState',
            'quickactionsDisabled',
            'quickactionClasses',
            'sizingModeOptions',
            'mobileBehaviorOptions',
            'backgroundMediaModeOptions',
        ],
        methods: [
            'onSetBackgroundMedia',
            'successfulUpload',
            'removeMedia',
            'onSectionDelete',
            'onSectionDuplicate',
        ],
    },
});
