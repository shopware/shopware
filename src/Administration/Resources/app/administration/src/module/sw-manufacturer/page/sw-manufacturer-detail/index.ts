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
    name: 'sw-manufacturer-detail',
    load: () => import('./src/sw-manufacturer-detail'),
    public: {
        props: [
            'manufacturerId',
        ],
        blocks: [
            'sw_manufacturer_detail',
            'sw_manufacturer_detail_header',
            'sw_manufacturer_detail_actions',
            'sw_manufacturer_detail_actions_abort',
            'sw_manufacturer_detail_actions_save',
            'sw_manufacturer_detail_language_switch',
            'sw_manufacturer_detail_content',
            'sw_manufacturer_detail_content_language_info',
            'sw_manufacturer_detail_base_basic_info_card',
            'sw_manufacturer_detail_base_info_field_name',
            'sw_manufacturer_detail_base_info_field_link',
            'sw_manufacturer_detail_logo_upload',
            'sw_manufacturer_detail_base_info_field_description',
            'sw_manufacturer_detail_custom_field_sets',
            'sw_manufacturer_detail_sidebar',
        ],
        computed: [
            'identifier',
            'manufacturerIsLoading',
            'manufacturerRepository',
            'mediaRepository',
            'customFieldSetRepository',
            'customFieldSetCriteria',
            'mediaUploadTag',
            'tooltipSave',
            'tooltipCancel',
        ],
        methods: [
            'createdComponent',
            'loadEntityData',
            'abortOnLanguageChange',
            'saveOnLanguageChange',
            'onChangeLanguage',
            'setMediaItem',
            'setMediaFromSidebar',
            'onUnlinkLogo',
            'openMediaSidebar',
            'onDropMedia',
            'onMediaSelectionChange',
            'getMediaDefaultFolderId',
            'onSave',
            'onCancel',
        ],
    },
});
